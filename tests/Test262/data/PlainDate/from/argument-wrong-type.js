// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: Appropriate error thrown when argument cannot be converted to a valid string or property bag
features: [Temporal]
---*/

assert.throws(TypeError, () => Temporal.PlainDate.from(), "no argument");
assert.throws(TypeError, () => Temporal.PlainDate.from(undefined), "undefined");
assert.throws(TypeError, () => Temporal.PlainDate.from(null), "null");
assert.throws(TypeError, () => Temporal.PlainDate.from(true), "boolean");
assert.throws(TypeError, () => Temporal.PlainDate.from(1), "number");
assert.throws(RangeError, () => Temporal.PlainDate.from(""), "empty string");
