// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.equals
description: Appropriate error thrown when argument cannot be converted to a valid string or property bag
features: [Temporal]
---*/

const instance = new Temporal.PlainDate(2000, 5, 2);

assert.throws(TypeError, () => instance.equals(undefined), "undefined");
assert.throws(TypeError, () => instance.equals(null), "null");
assert.throws(TypeError, () => instance.equals(true), "boolean");
assert.throws(TypeError, () => instance.equals(1), "number");
assert.throws(RangeError, () => instance.equals(""), "empty string");
