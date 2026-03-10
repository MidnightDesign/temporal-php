// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.compare
description: TypeError thrown when either argument cannot be converted to PlainDate
features: [Temporal, arrow-function]
---*/

const d = new Temporal.PlainDate(2000, 5, 2);

assert.throws(TypeError, () => Temporal.PlainDate.compare(undefined, d), "undefined, first arg");
assert.throws(TypeError, () => Temporal.PlainDate.compare(null, d), "null, first arg");
assert.throws(TypeError, () => Temporal.PlainDate.compare(true, d), "boolean, first arg");
assert.throws(RangeError, () => Temporal.PlainDate.compare("", d), "empty string, first arg");

assert.throws(TypeError, () => Temporal.PlainDate.compare(d, undefined), "undefined, second arg");
assert.throws(TypeError, () => Temporal.PlainDate.compare(d, null), "null, second arg");
assert.throws(TypeError, () => Temporal.PlainDate.compare(d, true), "boolean, second arg");
assert.throws(RangeError, () => Temporal.PlainDate.compare(d, ""), "empty string, second arg");
