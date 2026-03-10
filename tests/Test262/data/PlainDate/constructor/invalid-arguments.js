// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate
description: RangeError thrown for invalid month and day values
features: [Temporal, arrow-function]
---*/

assert.throws(RangeError, () => new Temporal.PlainDate(2020, 0, 1), "month 0");
assert.throws(RangeError, () => new Temporal.PlainDate(2020, 13, 1), "month 13");
assert.throws(RangeError, () => new Temporal.PlainDate(2020, 1, 0), "day 0");
assert.throws(RangeError, () => new Temporal.PlainDate(2020, 1, 32), "day 32 in January");
assert.throws(RangeError, () => new Temporal.PlainDate(2020, 4, 31), "day 31 in April");
assert.throws(RangeError, () => new Temporal.PlainDate(2021, 2, 29), "day 29 in non-leap Feb");
assert.throws(RangeError, () => new Temporal.PlainDate(2020, 2, 30), "day 30 in leap Feb");
