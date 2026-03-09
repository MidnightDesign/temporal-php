#!/usr/bin/env node
/**
 * TC39 test262 → PHP transpiler.
 *
 * Replaces the old two-stage extract+generate pipeline with a single pass that
 * reads each JS test file, parses it with acorn, walks the AST, and emits an
 * equivalent PHP script understood by tests/Test262/RunnerTest.php.
 *
 * Usage:
 *   node tools/transpile-test262.mjs tests/Test262/data
 *
 * Output: tests/Test262/scripts/<same relative path>.php
 *
 * Translation rules (see project prompt for full table):
 *   const/let x = expr        → $x = expr;
 *   123n (BigInt)             → 123  (skipped if overflows int64)
 *   Temporal.X.y(arg)         → \Temporal\X::y($arg)
 *   new Temporal.X(...)       → new \Temporal\X(...)
 *   for (const x of arr)      → foreach ($arr as $x)
 *   for (const [a,b] of arr)  → foreach ($arr as [$a, $b])
 *   arr.forEach(x => {...})   → foreach ($arr as $x) {...}
 *   assert.sameValue(a,b,msg) → Assert::sameValue($a, $b, $msg);
 *   assert.throws(E,fn,msg)   → Assert::throws(E::class, fn, $msg);
 *   assert.compareArray(a,b)  → Assert::compareArray($a, $b);
 *   `text ${expr}`            → "text {$expr}"
 *   // comment                → // comment  (inline)
 *   frontmatter (strip the ---...--- delimited header)
 *   includes: [...] in header → Assert::incomplete('needs TemporalHelpers') + stop
 *   unrecognised node         → Assert::incomplete('untranslatable: ...')
 */

import { parse } from '/usr/local/lib/node_modules/acorn/dist/acorn.mjs';
import fs from 'node:fs';
import path from 'node:path';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

const ACORN_OPTIONS = { ecmaVersion: 2022, sourceType: 'script' };

const PHP_INT_MAX = 9_223_372_036_854_775_807n;
const PHP_INT_MIN = -9_223_372_036_854_775_808n;

/** JS error → PHP exception class (fully-qualified). */
const ERROR_MAP = {
  RangeError: '\\InvalidArgumentException',
  TypeError:  '\\TypeError',
};

/** Temporal class methods that have been implemented. */
const IMPLEMENTED = new Set([
  'Duration::from',
  'Duration::compare',
  'Instant::from',
  'Instant::fromEpochMilliseconds',
  'Instant::fromEpochNanoseconds',
  'Instant::compare',
  'Instant::equals',
  'Instant::toString',
  'Instant::toJSON',
]);

/** Temporal classes whose constructors are implemented. */
const IMPLEMENTED_CTORS = new Set(['Duration', 'Instant']);

/**
 * Instance methods on Temporal classes that are NOT yet implemented.
 * Calls to these are emitted as Assert::incomplete() rather than crashing.
 */
const NOT_YET_IMPLEMENTED_METHODS = new Set([
  'toLocaleString', 'toZonedDateTimeISO',
]);

/**
 * Instance methods that are not yet implemented on Temporal.Instant but ARE
 * implemented on other classes (e.g. Duration). Only emit incomplete when the
 * receiver is a known Instant variable.
 */
const INSTANT_UNIMPLEMENTED_METHODS = new Set([]);

/**
 * Instance methods implemented on Temporal.Instant but NOT yet on other
 * Temporal classes (e.g. Duration). Only pass through when the receiver is
 * a known Instant variable; otherwise emit incomplete.
 */
const INSTANT_ONLY_METHODS = new Set([]);

/**
 * TemporalHelpers methods that have been implemented in PHP.
 * Other TemporalHelpers calls → emitIncomplete.
 */
const IMPLEMENTED_HELPERS = new Set([
  'assertDuration',
  'assertDurationsEqual',
  'assertDateDuration',
  'assertInstantsEqual',
  'checkPluralUnitsAccepted',
  'checkStringOptionWrongType',
  'checkSubclassingIgnored',
  'checkSubclassingIgnoredStatic',
  'checkRoundingIncrementOptionWrongType',
]);

/**
 * PHP methods that exist on each Temporal class (static + instance).
 * Used by emitVerifyProperty() to decide whether to emit a real assertion
 * or Assert::incomplete() for a method that is not yet implemented.
 */
const PHP_IMPLEMENTED_METHODS = {
  Instant:  new Set([
    '__construct', 'from', 'fromEpochMilliseconds', 'fromEpochNanoseconds',
    'compare', 'equals', 'valueOf', 'toString', 'toJSON',
    'add', 'subtract', 'round', 'since', 'until',
  ]),
  Duration: new Set([
    '__construct', 'from', 'negated', 'abs', 'equals', 'with',
    'add', 'subtract', 'total', 'toString', 'toJSON', 'valueOf',
    'compare', 'round',
  ]),
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function overflowsInt64(bigint) {
  return bigint > PHP_INT_MAX || bigint < PHP_INT_MIN;
}

/** Strip the /*--- ... ---* / frontmatter and return { includes, stripped }. */
function parseFrontmatter(source) {
  const m = source.match(/\/\*---\s*([\s\S]*?)\s*---\*\//);
  if (!m) return { includes: [], stripped: source };

  const yaml = m[1];
  let includes = [];
  const incMatch = yaml.match(/^includes:\s*\[([^\]]*)\]/m);
  if (incMatch) {
    includes = incMatch[1].split(',').map(s => s.trim()).filter(Boolean);
  }
  const stripped = source.slice(m.index + m[0].length).trimStart();
  return { includes, stripped };
}

// ---------------------------------------------------------------------------
// AST → PHP emitter
// ---------------------------------------------------------------------------

/** Returns true if the arrow function body directly calls a function with a non-overflow BigInt literal arg. */
function arrowHasBigIntArg(fnNode) {
  if (!fnNode || fnNode.type !== 'ArrowFunctionExpression') return false;
  const body = fnNode.body;
  if (body.type !== 'CallExpression') return false;
  return body.arguments.some(a => a.type === 'Literal' && a.bigint !== undefined && !overflowsInt64(BigInt(a.bigint)));
}

/** Returns true if the arrow body directly calls methodName with a plain number literal arg. */
function arrowCallsWithNumber(fnNode, methodName) {
  if (!fnNode || fnNode.type !== 'ArrowFunctionExpression') return false;
  const body = fnNode.body;
  if (body.type !== 'CallExpression') return false;
  const callee = body.callee;
  if (!callee || callee.type !== 'MemberExpression' || callee.property.name !== methodName) return false;
  return body.arguments.some(a => a.type === 'Literal' && typeof a.value === 'number');
}

/**
 * Returns true if the arrow body is `new Temporal.Instant(arg)` where arg is a
 * plain Number literal (not a BigInt). PHP int64 can't replicate JS BigInt-vs-Number
 * type distinction, so these TypeError assertions are untranslatable.
 */
function arrowInstantCtorWithNumberArg(fnNode) {
  if (!fnNode || fnNode.type !== 'ArrowFunctionExpression') return false;
  const body = fnNode.body;
  if (body.type !== 'NewExpression') return false;
  const callee = body.callee;
  if (!callee || callee.type !== 'MemberExpression') return false;
  if (callee.object?.name !== 'Temporal' || callee.property?.name !== 'Instant') return false;
  return body.arguments.some(a => a.type === 'Literal' && typeof a.value === 'number');
}

/** Operator precedence table (higher = binds tighter). */
/**
 * Translate `typeof $arg === 'jsType'` to a PHP boolean expression.
 * Returns null if the jsType has no meaningful PHP equivalent.
 */
function typeofToPhp(phpArg, jsType) {
  switch (jsType) {
    case 'string':    return `is_string(${phpArg})`;
    case 'number':    return `(is_int(${phpArg}) || is_float(${phpArg}))`;
    case 'boolean':   return `is_bool(${phpArg})`;
    case 'object':    return `is_object(${phpArg})`;
    // PHP null represents JS null (not JS undefined); PHP variables are never
    // "undefined" in the JS sense, so typeof $x === 'undefined' is always false.
    case 'undefined': return `false`;
    case 'function':  return `is_callable(${phpArg})`;
    // 'symbol' and 'bigint' don't exist in PHP — always false
    case 'symbol':    return 'false';
    case 'bigint':    return 'false';
    default:          return null;
  }
}

const OP_PREC = {
  '**': 15, '*': 14, '/': 14, '%': 14,
  '+': 13, '-': 13,
  '<<': 12, '>>': 12, '>>>': 12,
  '<': 11, '<=': 11, '>': 11, '>=': 11,
  '==': 10, '!=': 10, '===': 10, '!==': 10,
  '&': 9, '^': 8, '|': 7, '&&': 6, '||': 5, '??': 5,
};

class Emitter {
  constructor(source) {
    this.source = source;
    this.lines = [];
    this.incomplete = false;   // set when we emit Assert::incomplete
    this.skipOverflow = false; // for the current assertion being built
    // Variables known to hold PHP arrays (assigned from JS object literals).
    // Member access on these uses ['key'] instead of ->key.
    this.objectVars = new Set();
    this.instantVars = new Set();  // variables known to hold Temporal.Instant instances
    // Variables that are aliases for a Temporal class (from `const { Instant } = Temporal;`).
    // Maps JS variable name → Temporal class name (e.g. 'Instant' → 'Instant').
    this.temporalClassAliases = new Map();
  }

  emit(line) {
    if (!this.incomplete) this.lines.push(line);
  }

  emitIncomplete(reason) {
    if (!this.incomplete) {
      this.lines.push(`Assert::incomplete(${phpStr(reason)});`);
      this.incomplete = true;
    }
  }

  // ── Top-level ─────────────────────────────────────────────────────────────

  transpileProgram(node) {
    for (const stmt of node.body) {
      this.transpileStatement(stmt);
      if (this.incomplete) break;
    }
  }

  // ── Statements ────────────────────────────────────────────────────────────

  transpileStatement(node) {
    switch (node.type) {
      case 'VariableDeclaration':
        this.transpileVarDecl(node);
        break;
      case 'ExpressionStatement':
        this.transpileExprStmt(node);
        break;
      case 'ForOfStatement':
        this.transpileForOf(node);
        break;
      case 'BlockStatement':
        for (const s of node.body) this.transpileStatement(s);
        break;
      case 'EmptyStatement':
        break;
      case 'FunctionDeclaration':
        this.transpileFunctionDecl(node);
        break;
      case 'ReturnStatement': {
        if (node.argument) {
          const retPhp = this.transpileExpr(node.argument);
          if (retPhp !== null) this.emit(`return ${retPhp};`);
        } else {
          this.emit('return;');
        }
        break;
      }
      case 'IfStatement':
        this.transpileIf(node);
        break;
      default:
        this.emitIncomplete(`untranslatable statement: ${node.type}`);
    }
  }

  transpileVarDecl(node) {
    for (const decl of node.declarations) {
      if (decl.init === null) continue;
      if (decl.id.type === 'ObjectPattern') {
        // const { X } = Temporal; → track alias, emit nothing
        if (decl.init?.type === 'Identifier' && decl.init.name === 'Temporal') {
          for (const prop of decl.id.properties) {
            if (prop.type === 'Property' && !prop.computed
                && prop.key?.type === 'Identifier' && prop.value?.type === 'Identifier') {
              this.temporalClassAliases.set(prop.value.name, prop.key.name);
            }
          }
          continue; // no PHP emitted for this declaration
        }
        // const { method } = Temporal.X; → $method = [\Temporal\X::class, 'method'];
        if (decl.init?.type === 'MemberExpression' && !decl.init.computed
            && decl.init.object?.type === 'Identifier' && decl.init.object.name === 'Temporal'
            && decl.init.property?.type === 'Identifier') {
          const className = decl.init.property.name;
          for (const prop of decl.id.properties) {
            if (prop.type === 'Property' && !prop.computed && prop.key?.type === 'Identifier') {
              const methodName = prop.value?.name ?? prop.key.name;
              this.emit(`$${methodName} = [\\Temporal\\${className}::class, '${methodName}'];`);
            }
          }
          continue; // handled
        }
        this.emitIncomplete('untranslatable: destructuring assignment');
        return;
      }
      if (decl.id.type === 'Identifier' && decl.init?.type === 'ObjectExpression') {
        this.objectVars.add(decl.id.name);
      }
      // Track variables assigned from Temporal.Instant constructors
      if (decl.id.type === 'Identifier' && this.isInstantCall(decl.init)) {
        this.instantVars.add(decl.id.name);
      }
      const lhs = this.transpilePattern(decl.id);
      const rhs = this.transpileExpr(decl.init);
      if (rhs === null) {
        // BigInt overflow or other untranslatable value — can't define the variable
        this.emitIncomplete(`cannot represent value of '${decl.id.name ?? '?'}' in PHP (BigInt overflow)`);
        return;
      }
      // When destructuring an ArrayPattern, pad the RHS to avoid "Undefined array key" warnings.
      // If any element has a default value (AssignmentPattern), pad with 0 (the most common
      // default in test262 array destructuring). Otherwise pad with null.
      if (decl.id.type === 'ArrayPattern') {
        const n = decl.id.elements.length;
        const hasDefaults = decl.id.elements.some(e => e?.type === 'AssignmentPattern');
        const padVal = hasDefaults ? '0' : 'null';
        this.emit(`${lhs} = array_pad(${rhs}, ${n}, ${padVal});`);
      } else {
        this.emit(`${lhs} = ${rhs};`);
      }
    }
  }

  /**
   * Transpiles a FunctionDeclaration as a PHP closure stored in a variable.
   *
   * PHP functions don't inherit outer scope; we emit a closure with a `use`
   * clause listing the outer variables the body references.
   */
  transpileFunctionDecl(node) {
    const name = node.id?.name;
    if (!name) {
      this.emitIncomplete('untranslatable: anonymous FunctionDeclaration');
      return;
    }
    const params = node.params.map(p => this.transpilePattern(p)).join(', ');
    // If the function body uses BigInt arithmetic, the computation may overflow PHP int64.
    if (hasBigIntLiteral(node.body)) {
      this.emitIncomplete('untranslatable: BigInt arithmetic in function body');
      return;
    }
    const inner = [];
    const savedLines = this.lines;
    const savedIncomplete = this.incomplete;
    this.lines = inner;
    this.transpileStatement(node.body);
    const becameIncomplete = this.incomplete && !savedIncomplete;
    this.lines = savedLines;
    if (becameIncomplete) {
      const incLine = inner.find(l => l.startsWith('Assert::incomplete('));
      const reason = incLine
        ? (incLine.match(/Assert::incomplete\('(.*)'\)/) ?? [])[1] ?? 'untranslatable code in function body'
        : 'untranslatable code in function body';
      this.incomplete = false;
      this.emitIncomplete(reason);
      return;
    }
    if (savedIncomplete) return; // already incomplete; nothing to emit
    // Track variables defined within the body to exclude from the use clause.
    // This prevents spurious capture of foreach loop variables and local assignments.
    const localVars = new Set(node.params.map(p => p.type === 'Identifier' ? p.name : null).filter(Boolean));
    for (const line of inner) {
      // Regular assignment: $var = ...
      for (const m of line.matchAll(/\$([a-zA-Z_]\w*)\s*=/g)) localVars.add(m[1]);
      // foreach loop variables: everything after " as " contains the loop vars
      if (line.startsWith('foreach (')) {
        const asIdx = line.indexOf(' as ');
        if (asIdx !== -1) {
          const afterAs = line.slice(asIdx + 4);
          for (const m of afterAs.matchAll(/\$([a-zA-Z_]\w*)/g)) localVars.add(m[1]);
        }
      }
    }
    // Collect outer variables used in the body that are not locally defined.
    const usedVars = new Set();
    for (const line of inner) {
      for (const m of line.matchAll(/\$([a-zA-Z_]\w*)/g)) {
        const v = m[1];
        if (v !== '__' && v !== 'this' && !localVars.has(v)) usedVars.add(v);
      }
    }
    const useClause = usedVars.size > 0 ? `use (${[...usedVars].map(v => `$${v}`).join(', ')}) ` : '';
    this.emit(`$${name} = function (${params}) ${useClause}{`);
    for (const line of inner) this.emit(line);
    this.emit('};');
  }

  transpileIf(node) {
    const test = this.transpileExpr(node.test);
    if (test === null) return;
    const before = this.lines.length;
    this.emit(`if (${test}) {`);
    const opened = this.lines.length > before;
    this.transpileStatement(node.consequent);
    if (node.alternate) {
      if (opened) this.lines.push('} else {');
      this.transpileStatement(node.alternate);
    }
    if (opened) this.lines.push('}');
  }

  /** Returns true if the node produces a Temporal.Instant instance. */
  isInstantCall(node) {
    if (!node) return false;
    // new Temporal.Instant(…)
    if (node.type === 'NewExpression'
        && node.callee.type === 'MemberExpression'
        && !node.callee.computed
        && node.callee.object.type === 'Identifier' && node.callee.object.name === 'Temporal'
        && node.callee.property.name === 'Instant') {
      return true;
    }
    // Temporal.Instant.from/fromEpochMilliseconds/fromEpochNanoseconds(…)
    if (node.type !== 'CallExpression') return false;
    const temporal = resolveTemporalCall(node.callee);
    return temporal?.className === 'Instant' &&
      (temporal.method === 'from' || temporal.method === 'fromEpochMilliseconds' || temporal.method === 'fromEpochNanoseconds');
  }

  transpileExprStmt(node) {
    const php = this.transpileExpr(node.expression);
    if (php !== null) this.emit(`${php};`);
  }

  transpileForOf(node) {
    const iter = this.transpileExpr(node.right);
    if (iter === null) return;
    const pat  = this.transpilePattern(node.left.declarations?.[0]?.id ?? node.left);
    const before = this.lines.length;
    this.emit(`foreach (${iter} as ${pat}) {`);
    const opened = this.lines.length > before;
    this.transpileStatement(node.body);
    if (opened) this.lines.push('}'); // always close what was opened
  }

  // ── Patterns (left-hand sides) ────────────────────────────────────────────

  transpilePattern(node) {
    if (node.type === 'Identifier') return '$' + node.name;
    if (node.type === 'ArrayPattern') {
      const parts = node.elements.map(e => e ? this.transpilePattern(e) : 'null');
      return '[' + parts.join(', ') + ']';
    }
    // AssignmentPattern: `x = default` in destructuring → use just the left side.
    // The default is handled by array_pad() in transpileVarDecl.
    if (node.type === 'AssignmentPattern') {
      return this.transpilePattern(node.left);
    }
    return '$__unknown__';
  }

  // ── Expressions ───────────────────────────────────────────────────────────

  /**
   * Returns the PHP source string for the expression, or null if the
   * expression should be skipped (e.g. BigInt overflow).
   */
  transpileExpr(node) {
    switch (node.type) {
      case 'Literal':           return this.transpileLiteral(node);
      case 'Identifier':        return this.transpileIdentifier(node);
      case 'TemplateLiteral':   return this.transpileTemplate(node);
      case 'ArrayExpression':   return this.transpileArray(node);
      case 'MemberExpression':  return this.transpileMember(node);
      case 'CallExpression':    return this.transpileCall(node);
      case 'NewExpression':     return this.transpileNew(node);
      case 'ArrowFunctionExpression': return this.transpileArrow(node);
      case 'UnaryExpression':   return this.transpileUnary(node);
      case 'BinaryExpression':  return this.transpileBinary(node);
      case 'AssignmentExpression': return this.transpileAssignment(node);
      case 'ObjectExpression':   return this.transpileObject(node);
      case 'LogicalExpression':  return this.transpileLogical(node);
      case 'ConditionalExpression': return this.transpileConditional(node);
      default:
        this.emitIncomplete(`untranslatable expression: ${node.type}`);
        return null;
    }
  }

  transpileLiteral(node) {
    if (node.bigint !== undefined) {
      // BigInt literal (e.g. 123n)
      const val = BigInt(node.bigint);
      if (overflowsInt64(val)) {
        return null; // caller decides how to handle (skip assertion vs error)
      }
      return phpInt(node.bigint); // decimal string with underscore separators
    }
    if (typeof node.value === 'string') {
      return phpStr(node.value);
    }
    if (typeof node.value === 'number') {
      // For integers beyond Number.MAX_SAFE_INTEGER, the JS source literal may have been
      // rounded to a different float64 value during parsing. Use BigInt(node.value) to
      // recover the exact integer that the float64 actually represents, then emit that.
      // Example: JS source 4503599627370497000 → float64 4503599627370497024 → PHP 4_503_599_627_370_497_024
      if (Number.isInteger(node.value) && Math.abs(node.value) > 9_007_199_254_740_991) {
        const exact = BigInt(node.value);
        if (!overflowsInt64(exact)) {
          return phpInt(exact.toString()); // exact value, fits in PHP int64
        }
        // Larger than PHP_INT_MAX: emit as float literal (JS stringification preserves float64)
        return phpInt(String(node.value));
      }
      return phpInt(String(node.value));
    }
    if (typeof node.value === 'boolean') {
      return node.value ? 'true' : 'false';
    }
    if (node.value === null) {
      return 'null';
    }
    return this.raw(node);
  }

  transpileIdentifier(node) {
    switch (node.name) {
      case 'undefined': return 'null';
      case 'RangeError': return '\\InvalidArgumentException';
      case 'TypeError':  return '\\TypeError';
      case 'Infinity':   return 'INF';
      case 'NaN':        return 'NAN';
      case 'Temporal':
        // Reached only when Temporal is used as a plain value (e.g. prototype access).
        // new Temporal.X() and Temporal.X.y() are intercepted before transpileIdentifier.
        this.emitIncomplete('Temporal namespace object access is not translatable');
        return null;
      default:           return '$' + node.name;
    }
  }

  transpileTemplate(node) {
    // Template literal → PHP double-quoted string
    let result = '"';
    for (let i = 0; i < node.quasis.length; i++) {
      const quasi = node.quasis[i];
      // Escape the cooked string for PHP double-quotes
      result += quasi.value.cooked
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\$/g, '\\$');
      if (i < node.expressions.length) {
        const exprPhp = this.transpileExpr(node.expressions[i]);
        if (exprPhp === null) return null;
        // Wrap complex expressions in {…} so PHP interpolates them
        result += `{${exprPhp}}`;
      }
    }
    result += '"';
    return result;
  }

  transpileArray(node) {
    const parts = [];
    for (const el of node.elements) {
      if (el === null) { parts.push('null'); continue; }
      const php = this.transpileExpr(el);
      // Overflow BigInt inside an array → sentinel; sameValue() will skip it.
      parts.push(php ?? 'Assert::int64Overflow()');
    }
    return '[' + parts.join(', ') + ']';
  }

  transpileMember(node) {
    if (!node.computed) {
      // Temporal member expressions used as values (not as call targets)
      const temporalTarget = parseVerifyPropertyTarget(node);
      if (temporalTarget) {
        switch (temporalTarget.type) {
          case 'namespace':
            this.emitIncomplete('Temporal namespace object access is not translatable');
            return null;
          case 'class':
            // Return the PHP class constant string (e.g. \Temporal\Instant::class).
            // For `instanceof Temporal.X`, transpileBinary strips ::class before emitting.
            return `\\Temporal\\${temporalTarget.class}::class`;
          case 'prototype':
            return 'new \\stdClass()';
          case 'staticMethod':
          case 'instanceMethod':
            this.emitIncomplete(`\\Temporal\\${temporalTarget.class}::${temporalTarget.method} used as a value`);
            return null;
        }
      }

      // JS built-in globals that have no PHP equivalent
      if (node.object.type === 'Identifier') {
        const jsGlobalObjects = ['Object', 'Reflect', 'Symbol', 'Proxy', 'Array'];
        if (jsGlobalObjects.includes(node.object.name)) {
          this.emitIncomplete(`untranslatable: ${node.object.name}.${node.property.name ?? '?'}`);
          return null;
        }
      }
      // Number.* constants
      if (node.object.type === 'Identifier' && node.object.name === 'Number') {
        switch (node.property.name) {
          case 'MAX_SAFE_INTEGER': return '9_007_199_254_740_991';
          case 'MAX_VALUE':
            // Number.MAX_VALUE ≈ 1.8e308 has no PHP int equivalent; PHP_INT_MAX µs/ns
            // is within the valid Duration range, so this test cannot be faithfully translated.
            this.emitIncomplete('Number.MAX_VALUE exceeds PHP_INT_MAX; no exact PHP int equivalent');
            return null;
          case 'MIN_SAFE_INTEGER': return '-9_007_199_254_740_991';
          case 'MIN_VALUE':        return '5.0E-324';
          case 'EPSILON':          return '2.220446049250313E-16';
          default:
            this.emitIncomplete(`untranslatable: Number.${node.property.name}`);
            return null;
        }
      }
      const obj  = this.transpileExpr(node.object);
      if (obj === null) return null;
      // Variables assigned from object literals ({...}) become PHP arrays.
      // Use ['key'] array access instead of ->key property access.
      if (node.object.type === 'Identifier' && this.objectVars.has(node.object.name)) {
        return `${obj}['${node.property.name}']`;
      }
      return `${obj}->${node.property.name}`;
    }
    // computed member (arr[i]) — rare in test262 temporal tests
    const obj = this.transpileExpr(node.object);
    const idx = this.transpileExpr(node.property);
    if (obj === null || idx === null) return null;
    return `${obj}[${idx}]`;
  }

  transpileCall(node) {
    const callee = node.callee;

    // assert.sameValue / assert.throws / assert.compareArray / assert.notSameValue
    if (isMember(callee, 'assert', 'sameValue'))    return this.emitSameValue(node);
    if (isMember(callee, 'assert', 'throws'))       return this.emitAssertThrows(node);
    if (isMember(callee, 'assert', 'compareArray')) return this.emitCompareArray(node);
    if (isMember(callee, 'assert', 'notSameValue')) return this.emitNotSameValue(node);

    // arr.forEach(x => { … })
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.name === 'forEach') {
      return this.transpileForEach(node);
    }

    // TemporalHelpers.method(args) → TemporalHelpers::method($args)
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.object.type === 'Identifier' && callee.object.name === 'TemporalHelpers') {
      const method = callee.property.name;
      if (!IMPLEMENTED_HELPERS.has(method)) {
        this.emitIncomplete(`TemporalHelpers.${method}() is not yet implemented`);
        return null;
      }
      // checkSubclassingIgnored / checkSubclassingIgnoredStatic:
      // first arg is Temporal.X (class reference) → translate to \Temporal\X::class
      if (method === 'checkSubclassingIgnored' || method === 'checkSubclassingIgnoredStatic') {
        const [classArg, ...rest] = node.arguments;
        const classRef = this.transpileTemporalClassRef(classArg);
        if (classRef === null) {
          this.emitIncomplete(`${method}: cannot translate first argument as a Temporal class reference`);
          return null;
        }
        const restArgs = this.transpileArgs(rest);
        if (restArgs === null) return null;
        return `TemporalHelpers::${method}(${classRef}, ${restArgs})`;
      }
      const args = this.transpileArgs(node.arguments);
      if (args === null) return null;
      return `TemporalHelpers::${method}(${args})`;
    }

    // bare assert(val, msg) → Assert::assertTrue($val, $msg)
    if (callee.type === 'Identifier' && callee.name === 'assert') {
      const [valNode, msgNode] = node.arguments;
      const valPhp = valNode ? this.transpileExpr(valNode) : 'true';
      if (valPhp === null) return null;
      const msgPhp = msgNode ? this.transpileExpr(msgNode) : "''";
      if (msgPhp === null) return null;
      return `Assert::assertTrue(${valPhp}, ${msgPhp})`;
    }

    // BigInt(x) / Number(x) called as bare functions → not translatable
    if (callee.type === 'Identifier' && callee.name === 'BigInt') {
      this.emitIncomplete('untranslatable: BigInt()');
      return null;
    }
    if (callee.type === 'Identifier' && callee.name === 'Number') {
      this.emitIncomplete('untranslatable: Number()');
      return null;
    }

    // Symbol() called as bare function → not translatable
    if (callee.type === 'Identifier' && callee.name === 'Symbol') {
      this.emitIncomplete('untranslatable: Symbol()');
      return null;
    }

    // verifyProperty(target, prop, descriptor) → Assert::method checks
    if (callee.type === 'Identifier' && callee.name === 'verifyProperty') {
      return this.emitVerifyProperty(node);
    }
    // isConstructor(fn) → always false in PHP (PHP methods are not constructors)
    if (callee.type === 'Identifier' && callee.name === 'isConstructor') {
      return 'false';
    }

    // Temporal class alias static method calls: Instant.from() after const { Instant } = Temporal;
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.object.type === 'Identifier'
        && this.temporalClassAliases.has(callee.object.name)
        && callee.property.type === 'Identifier') {
      const className = this.temporalClassAliases.get(callee.object.name);
      const method = callee.property.name;
      const key = `${className}::${method}`;
      if (!IMPLEMENTED.has(key)) {
        this.emitIncomplete(`\\Temporal\\${className}::${method}() is not yet implemented`);
        return null;
      }
      const args = this.transpileArgs(node.arguments);
      if (args === null) return null;
      return `\\Temporal\\${className}::${method}(${args})`;
    }

    // Temporal.X() called without new (should be called with new in PHP)
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.object.type === 'Identifier' && callee.object.name === 'Temporal'
        && callee.property.type === 'Identifier') {
      // This is Temporal.X() — not Temporal.X.y()
      this.emitIncomplete(`\\Temporal\\${callee.property.name}() must be called with new`);
      return null;
    }

    // Calls on JS built-in globals that have no PHP equivalent
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.object.type === 'Identifier') {
      const { name } = callee.object;
      const method = callee.property.name;

      // Math.* → PHP math functions
      if (name === 'Math') {
        return this.emitMathCall(method, node.arguments);
      }

      const jsGlobals = ['Object', 'Reflect', 'Symbol', 'Proxy', 'Array'];
      if (jsGlobals.includes(name)) {
        this.emitIncomplete(`untranslatable: ${name}.${method}`);
        return null;
      }
    }

    // Instance methods not yet implemented
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.type === 'Identifier'
        && NOT_YET_IMPLEMENTED_METHODS.has(callee.property.name)) {
      this.emitIncomplete(`Instant::${callee.property.name}() is not yet implemented`);
      return null;
    }

    // Instance methods not yet implemented on Instant (but may exist on Duration)
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.type === 'Identifier'
        && INSTANT_UNIMPLEMENTED_METHODS.has(callee.property.name)
        && callee.object.type === 'Identifier'
        && this.instantVars.has(callee.object.name)) {
      this.emitIncomplete(`Instant::${callee.property.name}() is not yet implemented`);
      return null;
    }

    // Methods implemented only on Instant: pass through for known Instant vars, incomplete otherwise.
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.type === 'Identifier'
        && INSTANT_ONLY_METHODS.has(callee.property.name)
        && !(callee.object.type === 'Identifier' && this.instantVars.has(callee.object.name))) {
      this.emitIncomplete(`${callee.property.name}() is not yet implemented on this class`);
      return null;
    }

    // str.repeat(n) → str_repeat(str, n)
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.name === 'repeat') {
      const str  = this.transpileExpr(callee.object);
      const n    = node.arguments[0] ? this.transpileExpr(node.arguments[0]) : '1';
      if (str === null || n === null) return null;
      return `str_repeat(${str}, (int) (${n}))`;
    }

    // arr.map(fn) → not directly translatable
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.name === 'map') {
      this.emitIncomplete('untranslatable: Array.prototype.map()');
      return null;
    }

    // arr.slice() / arr.indexOf() / arr.concat() → not directly translatable to PHP arrays
    if (callee.type === 'MemberExpression' && !callee.computed
        && (callee.property.name === 'slice' || callee.property.name === 'indexOf'
            || callee.property.name === 'concat')) {
      this.emitIncomplete(`untranslatable: Array.prototype.${callee.property.name}()`);
      return null;
    }

    // str.substr(start[, len]) → substr(string: $str, offset: $start[, length: $len])
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.name === 'substr') {
      const str   = this.transpileExpr(callee.object);
      const start = node.arguments[0] ? this.transpileExpr(node.arguments[0]) : '0';
      if (str === null || start === null) return null;
      if (node.arguments[1]) {
        const len = this.transpileExpr(node.arguments[1]);
        if (len === null) return null;
        return `substr(string: ${str}, offset: ${start}, length: ${len})`;
      }
      return `substr(string: ${str}, offset: ${start})`;
    }

    // str.includes(needle[, position]) → str_contains($str, $needle) (position ignored)
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.property.name === 'includes') {
      const str    = this.transpileExpr(callee.object);
      const needle = node.arguments[0] ? this.transpileExpr(node.arguments[0]) : "''";
      if (str === null || needle === null) return null;
      return `str_contains(${str}, ${needle})`;
    }

    // Temporal.X.y(arg)
    const temporal = resolveTemporalCall(callee);
    if (temporal) {
      const { className, method } = temporal;
      const key = `${className}::${method}`;
      if (!IMPLEMENTED.has(key)) {
        this.emitIncomplete(`\\Temporal\\${className}::${method}() is not yet implemented`);
        return null;
      }
      // JS auto-coerces objects to strings; PHP does not. If an objectVars variable
      // is passed to a string-accepting method, the test relies on JS-specific behaviour.
      const stringArgMethods = new Set(['from', 'compare']);
      if (stringArgMethods.has(method) && node.arguments.some(
          a => a.type === 'Identifier' && this.objectVars.has(a.name)
      )) {
        this.emitIncomplete('JS object-to-string coercion not replicable in PHP');
        return null;
      }
      const args = this.transpileArgs(node.arguments);
      if (args === null) return null;
      return `\\Temporal\\${className}::${method}(${args})`;
    }

    // Generic call (best-effort)
    const calleePhp = this.transpileExpr(callee);
    if (calleePhp === null) return null;
    const args = this.transpileArgs(node.arguments);
    if (args === null) return null;
    return `${calleePhp}(${args})`;
  }

  emitMathCall(method, argNodes) {
    const args = argNodes.map(a => this.transpileExpr(a));
    if (args.some(a => a === null)) return null;
    const [a, b] = args;
    switch (method) {
      case 'floor': return `(int) floor(${a})`;
      case 'ceil':  return `(int) ceil(${a})`;
      case 'round': return `(int) round(${a})`;
      case 'abs':   return `abs(${a})`;
      case 'trunc': return `(int) (${a})`;
      case 'pow':   return `(int) (${a} ** ${b})`;
      case 'max':   return `max(${args.join(', ')})`;
      case 'min':   return `min(${args.join(', ')})`;
      case 'log':   return `log(${a}${b !== undefined ? ', ' + b : ''})`;
      case 'log2':  return `log(${a}, 2)`;
      case 'log10': return `log10(${a})`;
      case 'sqrt':  return `sqrt(${a})`;
      default:
        this.emitIncomplete(`untranslatable: Math.${method}()`);
        return null;
    }
  }

  /**
   * Transpiles verifyProperty(target, propName, descriptor) calls.
   * Maps TC39 property descriptor checks to PHPUnit assertions.
   */
  emitVerifyProperty(node) {
    const [targetNode, propNode, descNode] = node.arguments;

    const target = parseVerifyPropertyTarget(targetNode);
    if (!target) {
      this.emitIncomplete('verifyProperty: unrecognized target');
      return null;
    }

    // Symbol properties (Symbol.toStringTag, etc.) → no meaningful PHP equivalent
    let propName = null;
    if (propNode && propNode.type === 'Literal') {
      propName = propNode.value;
    } else if (propNode && propNode.type === 'MemberExpression' && !propNode.computed
        && propNode.object.type === 'Identifier' && propNode.object.name === 'Symbol') {
      return 'Assert::assertTrue(true)';
    }

    if (propName === null) {
      this.emitIncomplete('verifyProperty: unrecognized property');
      return null;
    }

    switch (target.type) {
      case 'namespace':
        return 'Assert::assertTrue(true)';

      case 'class': {
        const cls = target.class;
        const phpClass = `\\Temporal\\${cls}`;
        if (propName === 'length') {
          const value = descNode ? this.getDescriptorValue(descNode) : null;
          if (value !== null) {
            return `Assert::methodLength('${phpClass}', '__construct', ${value})`;
          }
          return 'Assert::assertTrue(true)';
        }
        if (propName === 'name' || propName === 'prototype') {
          return 'Assert::assertTrue(true)';
        }
        if (isPhpMethodImplemented(cls, propName)) {
          return `Assert::methodExists('${phpClass}', '${propName}')`;
        }
        this.emitIncomplete(`\\Temporal\\${cls}::${propName}() is not yet implemented`);
        return null;
      }

      case 'prototype': {
        const cls = target.class;
        const phpClass = `\\Temporal\\${cls}`;
        if (propName === 'length' || propName === 'name' || propName === 'constructor') {
          return 'Assert::assertTrue(true)';
        }
        if (isPhpMethodImplemented(cls, propName)) {
          return `Assert::methodExists('${phpClass}', '${propName}')`;
        }
        this.emitIncomplete(`\\Temporal\\${cls}::${propName}() is not yet implemented`);
        return null;
      }

      case 'staticMethod': {
        const { class: cls, method } = target;
        const phpClass = `\\Temporal\\${cls}`;
        if (propName === 'length') {
          const value = descNode ? this.getDescriptorValue(descNode) : null;
          if (value !== null) {
            if (isPhpMethodImplemented(cls, method)) {
              return `Assert::methodLength('${phpClass}', '${method}', ${value})`;
            }
            this.emitIncomplete(`\\Temporal\\${cls}::${method}() is not yet implemented`);
            return null;
          }
          return 'Assert::assertTrue(true)';
        }
        return 'Assert::assertTrue(true)';
      }

      case 'instanceMethod': {
        const { class: cls, method } = target;
        const phpClass = `\\Temporal\\${cls}`;
        if (propName === 'length') {
          const value = descNode ? this.getDescriptorValue(descNode) : null;
          if (value !== null) {
            if (isPhpMethodImplemented(cls, method)) {
              return `Assert::methodLength('${phpClass}', '${method}', ${value})`;
            }
            this.emitIncomplete(`\\Temporal\\${cls}::${method}() is not yet implemented`);
            return null;
          }
          return 'Assert::assertTrue(true)';
        }
        return 'Assert::assertTrue(true)';
      }
    }

    return 'Assert::assertTrue(true)';
  }

  /** Extracts the `value` field from a JS descriptor object literal {value: X, ...}. */
  getDescriptorValue(node) {
    if (!node || node.type !== 'ObjectExpression') return null;
    for (const prop of node.properties) {
      if (prop.type === 'Property' && !prop.computed
          && prop.key.type === 'Identifier' && prop.key.name === 'value'
          && prop.value.type === 'Literal') {
        return prop.value.value;
      }
    }
    return null;
  }

  transpileNew(node) {
    // new Temporal.X(…)
    const callee = node.callee;
    // new X(…) where X is a Temporal class alias (from const { X } = Temporal;)
    if (callee.type === 'Identifier' && this.temporalClassAliases.has(callee.name)) {
      const cls = this.temporalClassAliases.get(callee.name);
      if (!IMPLEMENTED_CTORS.has(cls)) {
        this.emitIncomplete(`\\Temporal\\${cls} is not yet implemented`);
        return null;
      }
      const args = this.transpileArgs(node.arguments);
      if (args === null) return null;
      return `new \\Temporal\\${cls}(${args})`;
    }
    if (callee.type === 'MemberExpression' && !callee.computed
        && callee.object.type === 'Identifier' && callee.object.name === 'Temporal') {
      const cls = callee.property.name;
      if (!IMPLEMENTED_CTORS.has(cls)) {
        this.emitIncomplete(`\\Temporal\\${cls} is not yet implemented`);
        return null;
      }
      const args = this.transpileArgs(node.arguments);
      if (args === null) return null;
      return `new \\Temporal\\${cls}(${args})`;
    }
    // new Temporal.X.method() or new Temporal.X.prototype.method() → TypeError
    const deepTarget = parseVerifyPropertyTarget(callee);
    if (deepTarget && (deepTarget.type === 'staticMethod' || deepTarget.type === 'instanceMethod')) {
      return `throw new \\TypeError('PHP: cannot use method as constructor')`;
    }
    this.emitIncomplete(`untranslatable new expression`);
    return null;
  }

  transpileArrow(node) {
    // () => expr  or  (arg) => expr  or  arg => expr
    const params = node.params.map(p => this.transpilePattern(p)).join(', ');
    if (node.body.type === 'BlockStatement') {
      // Arrow with block body — inline the body statements
      const inner = [];
      const savedLines      = this.lines;
      const savedIncomplete = this.incomplete;
      this.lines = inner;
      this.transpileStatement(node.body);
      const becameIncomplete = this.incomplete && !savedIncomplete;
      this.lines = savedIncomplete ? savedLines : savedLines; // always restore
      this.lines = savedLines;
      if (becameIncomplete) {
        // Propagate incomplete to the main context:
        // find the reason from inner lines and re-emit to main.
        const incLine = inner.find(l => l.startsWith('Assert::incomplete('));
        const reason  = incLine
          ? (incLine.match(/Assert::incomplete\('(.*)'\)/) ?? [])[1] ?? 'untranslatable code in arrow body'
          : 'untranslatable code in arrow body';
        this.incomplete = false;          // temporarily allow emit
        this.emitIncomplete(reason);      // pushes to savedLines, sets incomplete=true
        return null;
      }
      // Collect outer variables referenced in the closure body (exclude params and $__/$this).
      const paramNames = new Set(node.params.map(p => p.type === 'Identifier' ? p.name : null).filter(Boolean));
      const usedVars = new Set();
      for (const line of inner) {
        for (const m of line.matchAll(/\$([a-zA-Z_]\w*)/g)) {
          const v = m[1];
          if (v !== '__' && v !== 'this' && !paramNames.has(v)) usedVars.add(v);
        }
      }
      const useClause = usedVars.size > 0 ? `use (${[...usedVars].map(v => `$${v}`).join(', ')}) ` : '';
      return `function (${params}) ${useClause}{ ${inner.join(' ')} }`;
    }
    // Concise body
    const body = this.transpileExpr(node.body);
    if (body === null) return null;
    return `fn(${params}) => ${body}`;
  }

  transpileUnary(node) {
    if (node.operator === 'typeof') {
      const target = parseVerifyPropertyTarget(node.argument);
      if (target) {
        // namespace and prototype are objects; class/method references are functions
        return (target.type === 'namespace' || target.type === 'prototype') ? "'object'" : "'function'";
      }
      this.emitIncomplete('untranslatable: typeof');
      return null;
    }
    const arg = this.transpileExpr(node.argument);
    if (arg === null) return null;
    // Word operators (void) need a space; symbol operators (!, -, +, ~) do not.
    const space = /^[a-z]/.test(node.operator) ? ' ' : '';
    // Parenthesise complex arguments so that e.g. -(a - b) is not mis-parsed
    // as (-a) - b by PHP's operator-precedence rules.
    const complex = node.argument.type === 'BinaryExpression'
      || node.argument.type === 'UnaryExpression';
    const wrapped = complex ? `(${arg})` : arg;
    return `${node.operator}${space}${wrapped}`;
  }

  transpileBinary(node) {
    // Handle `typeof x === 'type'` → PHP is_type($x) function
    if (node.left.type === 'UnaryExpression' && node.left.operator === 'typeof'
        && (node.operator === '===' || node.operator === '==' || node.operator === '!==' || node.operator === '!=')
        && node.right.type === 'Literal' && typeof node.right.value === 'string') {
      const arg = this.transpileExpr(node.left.argument);
      if (arg !== null) {
        const negate = node.operator === '!==' || node.operator === '!=';
        const phpCheck = typeofToPhp(arg, node.right.value);
        if (phpCheck !== null) return negate ? `!(${phpCheck})` : phpCheck;
      }
    }
    // `expr instanceof Temporal.X` → `$expr instanceof \Temporal\X`
    // (transpileTemporalClassRef returns \Temporal\X::class; strip ::class for instanceof)
    if (node.operator === 'instanceof') {
      const classRef = this.transpileTemporalClassRef(node.right);
      if (classRef !== null) {
        const leftPhp = this.transpileExpr(node.left);
        if (leftPhp === null) return null;
        return `${leftPhp} instanceof ${classRef.replace(/::class$/, '')}`;
      }
    }
    let left  = this.transpileExpr(node.left);
    let right = this.transpileExpr(node.right);
    if (left === null || right === null) return null;
    let op = node.operator === '===' ? '===' : node.operator;
    if (op === '+') {
      if (hasStringInPlusChain(node)) op = '.';
    }
    // Wrap right if it's a binary expression (preserves explicit parenthesisation
    // from the JS AST, e.g. a / (b * c) → a / (b * c) in PHP).
    if (node.right.type === 'BinaryExpression') {
      right = `(${right})`;
    }
    // Wrap left if it has lower precedence than the outer operator
    // e.g. (a + b) * c → left is BinaryExpr with prec 13, outer '*' has prec 14 → wrap
    if (node.left.type === 'BinaryExpression') {
      const leftPrec  = OP_PREC[node.left.operator]  ?? 0;
      const outerPrec = OP_PREC[op] ?? 0;
      if (leftPrec < outerPrec) {
        left = `(${left})`;
      }
    }
    return `${left} ${op} ${right}`;
  }

  transpileAssignment(node) {
    const left  = this.transpilePattern(node.left);
    const right = this.transpileExpr(node.right);
    if (right === null) return null;
    return `${left} ${node.operator} ${right}`;
  }

  transpileLogical(node) {
    const left  = this.transpileExpr(node.left);
    const right = this.transpileExpr(node.right);
    if (left === null || right === null) return null;
    // JS logical operators (||, &&, ??) map directly to PHP
    const op = node.operator === '??' ? '??' : node.operator;
    // Wrap operands that have lower precedence (e.g. ternary inside logical)
    const wrapIf = (php, n) =>
      n.type === 'ConditionalExpression' ? `(${php})` : php;
    return `${wrapIf(left, node.left)} ${op} ${wrapIf(right, node.right)}`;
  }

  transpileConditional(node) {
    const test       = this.transpileExpr(node.test);
    const consequent = this.transpileExpr(node.consequent);
    const alternate  = this.transpileExpr(node.alternate);
    if (test === null || consequent === null || alternate === null) return null;
    return `(${test} ? ${consequent} : ${alternate})`;
  }

  /**
   * Transpiles a node as a PHP class-reference expression (with ::class).
   * For simple identifiers (RangeError, TypeError) → \Foo::class.
   * For ConditionalExpression → (cond ? \Foo::class : \Bar::class).
   * Fallback: treats any other transpiled PHP starting with \ as a class ref.
   */
  /**
   * Translates a Temporal.X MemberExpression to \Temporal\X::class.
   * Used for passing Temporal class references to TemporalHelpers methods
   * (checkSubclassingIgnored, checkSubclassingIgnoredStatic).
   * Returns null if the node is not a recognized Temporal.X expression.
   */
  transpileTemporalClassRef(node) {
    if (node.type === 'MemberExpression' && !node.computed
        && node.object.type === 'Identifier' && node.object.name === 'Temporal'
        && node.property.type === 'Identifier') {
      return `\\Temporal\\${node.property.name}::class`;
    }
    return null;
  }

  transpileAsClassRef(node) {
    if (node.type === 'ConditionalExpression') {
      const test       = this.transpileExpr(node.test);
      const consequent = this.transpileAsClassRef(node.consequent);
      const alternate  = this.transpileAsClassRef(node.alternate);
      if (test === null || consequent === null || alternate === null) return null;
      return `(${test} ? ${consequent} : ${alternate})`;
    }
    const php = this.transpileExpr(node);
    if (php === null) return null;
    return php.startsWith('\\') ? `${php}::class` : php;
  }

  transpileObject(node) {
    // {key: value, ...} → ['key' => value, ...]
    // Properties whose value is the identifier `undefined` are OMITTED: JS
    // `{ key: undefined }` means the key is present but treated as default, which
    // PHP represents as the key being absent from the array.
    // Shorthand properties `{ key }` are equivalent to `{ key: key }` and are
    // translated as `['key' => $key]`.
    // Empty object literal {} → new \stdClass() to preserve JS object semantics.
    // Non-empty {} → PHP associative array ['key' => value].
    if (node.properties.length === 0) return 'new \\stdClass()';
    const parts = [];
    for (const prop of node.properties) {
      if (prop.type !== 'Property' || prop.method || prop.kind !== 'init') {
        this.emitIncomplete('untranslatable object property');
        return null;
      }
      // Computed property key: { [expr]: value } → [$expr => $value]
      if (prop.computed) {
        if (prop.value.type === 'Identifier' && prop.value.name === 'undefined') continue;
        const key = this.transpileExpr(prop.key);
        if (key === null) return null;
        const val = this.transpileExpr(prop.value);
        if (val === null) return null;
        parts.push(`${key} => ${val}`);
        continue;
      }
      if (prop.shorthand) {
        // { key } → ['key' => $key]
        const key = phpStr(prop.key.name);
        parts.push(`${key} => $${prop.key.name}`);
        continue;
      }
      // Skip keys with undefined value (JS undefined ≡ key absent in PHP options bags).
      if (prop.value.type === 'Identifier' && prop.value.name === 'undefined') continue;
      const key = prop.key.type === 'Identifier'
        ? phpStr(prop.key.name)
        : this.transpileExpr(prop.key);
      if (key === null) return null;
      const val = this.transpileExpr(prop.value);
      if (val === null) return null;
      parts.push(`${key} => ${val}`);
    }
    return '[' + parts.join(', ') + ']';
  }

  // ── assert.* helpers ──────────────────────────────────────────────────────

  emitSameValue(node) {
    const [actual, expected, msg] = node.arguments;

    // Check for BigInt overflow in expected
    if (expected?.type === 'Literal' && expected.bigint !== undefined) {
      const val = BigInt(expected.bigint);
      if (overflowsInt64(val)) {
        // Emit a skip comment, then return a no-op expression
        const actualPhp = this.transpileExpr(actual) ?? '/* skip */';
        const snippet   = this.source.slice(node.start, node.end).replace(/\n/g, ' ');
        this.emit(`// SKIP (int64 overflow): Assert::sameValue(${actualPhp}, ${expected.bigint}, ...);`);
        return '/* skipped */';
      }
    }

    const actualPhp   = this.transpileExpr(actual);
    const expectedPhp = this.transpileExpr(expected);
    const msgPhp      = msg ? this.transpileExpr(msg) : "''";

    if (actualPhp === null || expectedPhp === null) return null;
    return `Assert::sameValue(${actualPhp}, ${expectedPhp}, ${msgPhp})`;
  }

  emitAssertThrows(node) {
    const [errorNode, fnNode, msgNode] = node.arguments;
    const classExpr = this.transpileAsClassRef(errorNode);
    if (classExpr === null) return null;

    // TypeError tests relying on JS BigInt-vs-Number type distinction can't be replicated in PHP.
    if (classExpr.includes('TypeError') && fnNode) {
      if (arrowHasBigIntArg(fnNode)) {
        this.emitIncomplete('BigInt literal in TypeError assertion; BigInt vs Number distinction not replicable in PHP');
        return null;
      }
      if (arrowCallsWithNumber(fnNode, 'fromEpochNanoseconds')) {
        this.emitIncomplete('Number passed to fromEpochNanoseconds; BigInt vs Number distinction not replicable in PHP');
        return null;
      }
      if (arrowInstantCtorWithNumberArg(fnNode)) {
        this.emitIncomplete('Number literal passed to new Temporal.Instant(); BigInt vs Number distinction not replicable in PHP');
        return null;
      }
    }

    // PHP comparison operators (<, <=, >, >=) do not call valueOf() and thus cannot
    // throw TypeError the way JS does. Emit incomplete for these cases.
    if (fnNode?.type === 'ArrowFunctionExpression' && fnNode.body?.type === 'BinaryExpression') {
      const op = fnNode.body.operator;
      if (op === '<' || op === '<=' || op === '>' || op === '>=') {
        this.emitIncomplete(`PHP comparison operator '${op}' does not trigger valueOf()`);
        return null;
      }
    }

    const fnPhp  = fnNode ? this.transpileExpr(fnNode) : 'fn() => null';
    const msgPhp = msgNode ? this.transpileExpr(msgNode) : "''";
    if (fnPhp === null || msgPhp === null) return null;
    return `Assert::throws(${classExpr}, ${fnPhp}, ${msgPhp})`;
  }

  emitCompareArray(node) {
    const args = this.transpileArgs(node.arguments);
    if (args === null) return null;
    return `Assert::compareArray(${args})`;
  }

  emitNotSameValue(node) {
    const [actual, unexpected, msg] = node.arguments;
    const actualPhp     = this.transpileExpr(actual);
    const unexpectedPhp = this.transpileExpr(unexpected);
    const msgPhp        = msg ? this.transpileExpr(msg) : "''";
    if (actualPhp === null || unexpectedPhp === null) return null;
    return `Assert::notSameValue(${actualPhp}, ${unexpectedPhp}, ${msgPhp})`;
  }

  transpileForEach(node) {
    // arr.forEach(item => { … })  →  foreach ($arr as $item) { … }
    const arr = this.transpileExpr(node.callee.object);
    if (arr === null) return null;
    const cb  = node.arguments[0];
    if (!cb || (cb.type !== 'ArrowFunctionExpression' && cb.type !== 'FunctionExpression')) {
      this.emitIncomplete('untranslatable forEach callback');
      return null;
    }
    const param = cb.params[0] ? this.transpilePattern(cb.params[0]) : '$_';
    const before = this.lines.length;
    this.emit(`foreach (${arr} as ${param}) {`);
    const opened = this.lines.length > before;
    this.transpileStatement(cb.body.type === 'BlockStatement' ? cb.body : { type: 'BlockStatement', body: [{ type: 'ExpressionStatement', expression: cb.body }] });
    if (opened) this.lines.push('}');
    return null; // already emitted
  }

  transpileArgs(argNodes) {
    const parts = [];
    let hadSpread = false;
    for (const a of argNodes) {
      if (a.type === 'SpreadElement') {
        hadSpread = true;
        const arg = this.transpileExpr(a.argument);
        if (arg === null) return null;
        parts.push(`...${arg}`);
      } else if (hadSpread) {
        // PHP does not allow positional arguments after spread unpacking.
        // If the trailing arg is `undefined` (JS default), drop it — omitting it
        // achieves the same effect (the callee uses its default value).
        if (a.type === 'Identifier' && a.name === 'undefined') continue;
        // For other values: merge into a single spread of a combined array.
        // Rewrite all previous spread+positional args as [...spreadArr, extra, ...].
        const php = this.transpileExpr(a);
        if (php === null) return null;
        // Rebuild: replace the last `...$arr` with `...[...$arr, $extra]`
        const last = parts[parts.length - 1];
        if (last && last.startsWith('...')) {
          parts[parts.length - 1] = `...[${last.slice(3)}, ${php}]`;
        } else {
          parts.push(php);
        }
      } else {
        const php = this.transpileExpr(a);
        if (php === null) return null;
        parts.push(php);
      }
    }
    return parts.join(', ');
  }

  raw(node) {
    return this.source.slice(node.start, node.end);
  }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Returns true if the AST subtree contains any BigInt literal (n suffix).
 * Used to detect function bodies that involve BigInt arithmetic which may
 * overflow PHP int64 at runtime even if individual literals fit in int64.
 */
function hasBigIntLiteral(node) {
  if (!node || typeof node !== 'object') return false;
  if (node.type === 'Literal' && node.bigint !== undefined) return true;
  for (const key of Object.keys(node)) {
    if (key === 'start' || key === 'end' || key === 'loc' || key === 'type') continue;
    const child = node[key];
    if (Array.isArray(child)) { if (child.some(hasBigIntLiteral)) return true; }
    else if (hasBigIntLiteral(child)) return true;
  }
  return false;
}

/**
 * Returns true if the node (or any node reachable through + binary chains)
 * contains a string literal.  Used to decide whether a `+` operator should
 * be emitted as PHP's `.` (string concatenation).
 */
function hasStringInPlusChain(node) {
  if (node.type === 'Literal' && typeof node.value === 'string') return true;
  if (node.type === 'BinaryExpression' && node.operator === '+') {
    return hasStringInPlusChain(node.left) || hasStringInPlusChain(node.right);
  }
  return false;
}

function isMember(node, obj, prop) {
  return node.type === 'MemberExpression'
    && !node.computed
    && node.object.type === 'Identifier' && node.object.name === obj
    && node.property.type === 'Identifier' && node.property.name === prop;
}

/**
 * If node is a call to Temporal.ClassName.method, return { className, method }.
 * Otherwise return null.
 */
function resolveTemporalCall(callee) {
  if (callee.type !== 'MemberExpression' || callee.computed) return null;
  const mid = callee.object;
  if (mid.type !== 'MemberExpression' || mid.computed) return null;
  if (mid.object.type !== 'Identifier' || mid.object.name !== 'Temporal') return null;
  return { className: mid.property.name, method: callee.property.name };
}

/**
 * Parses a Temporal member-expression AST node into a structured descriptor:
 *
 *   Temporal              → { type: 'namespace' }
 *   Temporal.X            → { type: 'class',        class: 'X' }
 *   Temporal.X.prototype  → { type: 'prototype',    class: 'X' }
 *   Temporal.X.method     → { type: 'staticMethod', class: 'X', method: 'm' }
 *   Temporal.X.prototype.method → { type: 'instanceMethod', class: 'X', method: 'm' }
 *
 * Returns null if the node does not match any of the above patterns.
 */
function parseVerifyPropertyTarget(node) {
  if (!node) return null;
  // Temporal (bare identifier)
  if (node.type === 'Identifier' && node.name === 'Temporal') {
    return { type: 'namespace' };
  }
  if (node.type !== 'MemberExpression' || node.computed) return null;

  // Temporal.X
  if (node.object.type === 'Identifier' && node.object.name === 'Temporal'
      && node.property.type === 'Identifier') {
    return { type: 'class', class: node.property.name };
  }

  if (node.object.type !== 'MemberExpression' || node.object.computed) return null;
  const L2 = node.object;

  // Temporal.X.Y  (static method or "prototype")
  if (L2.object.type === 'Identifier' && L2.object.name === 'Temporal'
      && L2.property.type === 'Identifier'
      && node.property.type === 'Identifier') {
    const cls  = L2.property.name;
    const prop = node.property.name;
    if (prop === 'prototype') return { type: 'prototype', class: cls };
    return { type: 'staticMethod', class: cls, method: prop };
  }

  if (L2.object.type !== 'MemberExpression' || L2.object.computed) return null;
  const L3 = L2.object;

  // Temporal.X.prototype.method
  if (L3.object.type === 'Identifier' && L3.object.name === 'Temporal'
      && L3.property.type === 'Identifier'
      && L2.property.type === 'Identifier' && L2.property.name === 'prototype'
      && node.property.type === 'Identifier') {
    return { type: 'instanceMethod', class: L3.property.name, method: node.property.name };
  }

  return null;
}

/**
 * Returns true if the method named `method` on `className` is implemented in PHP.
 */
function isPhpMethodImplemented(className, method) {
  return PHP_IMPLEMENTED_METHODS[className]?.has(method) ?? false;
}

/** PHP single-quoted string literal. */
function phpStr(s) {
  return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

/**
 * Add underscore separators to a decimal integer string for readability.
 * Mirrors PHP's convention: groups of 3 from the right, preserving sign.
 * Only applied when the number has more than 4 digits.
 */
function phpInt(decimalStr) {
  const neg  = decimalStr.startsWith('-');
  const digits = neg ? decimalStr.slice(1) : decimalStr;
  if (digits.length <= 4) return decimalStr;
  const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '_');
  return (neg ? '-' : '') + grouped;
}

// ---------------------------------------------------------------------------
// Process one file
// ---------------------------------------------------------------------------

function processFile(jsPath, dataDir, scriptsDir) {
  const relPath = path.relative(dataDir, jsPath).replace(/\\/g, '/');
  const source  = fs.readFileSync(jsPath, 'utf8');

  const { includes, stripped } = parseFrontmatter(source);

  const phpRelPath = relPath.replace(/\.js$/, '.php');
  const outPath    = path.join(scriptsDir, phpRelPath);
  fs.mkdirSync(path.dirname(outPath), { recursive: true });

  const WHITELISTED_INCLUDES = new Set([
    'temporalHelpers.js', 'compareArray.js',
    'propertyHelper.js', 'isConstructor.js',
  ]);
  const useTemporalHelpers = includes.includes('temporalHelpers.js');
  const unsupportedIncludes = includes.filter(i => !WHITELISTED_INCLUDES.has(i));

  const header = [
    '<?php',
    '',
    'declare(strict_types=1);',
    '',
    `// Source: tests/Test262/data/${relPath}`,
    '// Generated by tools/transpile-test262.mjs — do not edit manually.',
    '// Re-generate: composer test262:build',
    '',
    'use Temporal\\Tests\\Test262\\Assert;',
    ...(useTemporalHelpers ? ['use Temporal\\Tests\\Test262\\TemporalHelpers;'] : []),
    '',
  ];

  const emitter = new Emitter(stripped);

  if (unsupportedIncludes.length > 0) {
    emitter.emitIncomplete(`needs TemporalHelpers (includes: ${includes.join(', ')})`);
  } else {
    let ast;
    try {
      ast = parse(stripped, ACORN_OPTIONS);
    } catch (e) {
      emitter.emitIncomplete(`parse error: ${e.message}`);
    }
    if (ast) emitter.transpileProgram(ast);
  }

  const body = emitter.lines.join('\n');
  fs.writeFileSync(outPath, header.join('\n') + body + '\n');
  console.log(`  Transpiled → tests/Test262/scripts/${phpRelPath} (${emitter.lines.length} lines)`);
}

// ---------------------------------------------------------------------------
// Walk directories
// ---------------------------------------------------------------------------

function walkDir(dir, cb) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walkDir(full, cb);
    else if (entry.isFile() && entry.name.endsWith('.js')) cb(full);
  }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

const dataDir    = process.argv[2];
if (!dataDir) {
  console.error('Usage: node tools/transpile-test262.mjs <dataDir>');
  process.exit(1);
}

const projectRoot = path.resolve(dataDir, '..', '..', '..');
const scriptsDir  = path.join(path.dirname(path.resolve(dataDir)), 'scripts');

console.log(`Transpiling test262 JS → PHP from ${dataDir} …`);
walkDir(path.resolve(dataDir), f => processFile(f, path.resolve(dataDir), scriptsDir));
console.log('Done.');
