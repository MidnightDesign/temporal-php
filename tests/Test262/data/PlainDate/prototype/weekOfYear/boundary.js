// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.weekofyear
description: ISO week year boundaries — dates at the end of December can be in week 1 of the next year
features: [Temporal]
---*/

// 2020-12-28 is Monday of week 53 of 2020
assert.sameValue(new Temporal.PlainDate(2020, 12, 28).weekOfYear, 53, "2020-12-28 in week 53");
assert.sameValue(new Temporal.PlainDate(2020, 12, 28).yearOfWeek, 2020, "2020-12-28 in year 2020");

// 2021-01-03 is the last day of ISO week 53 of 2020
assert.sameValue(new Temporal.PlainDate(2021, 1, 3).weekOfYear, 53, "2021-01-03 in week 53");
assert.sameValue(new Temporal.PlainDate(2021, 1, 3).yearOfWeek, 2020, "2021-01-03 in year 2020");

// 2021-01-04 is week 1 of 2021
assert.sameValue(new Temporal.PlainDate(2021, 1, 4).weekOfYear, 1, "2021-01-04 in week 1");
assert.sameValue(new Temporal.PlainDate(2021, 1, 4).yearOfWeek, 2021, "2021-01-04 in year 2021");

// 2015-12-31 is in week 53 of 2015
assert.sameValue(new Temporal.PlainDate(2015, 12, 31).weekOfYear, 53, "2015-12-31 in week 53");
assert.sameValue(new Temporal.PlainDate(2015, 12, 31).yearOfWeek, 2015, "2015-12-31 in year 2015");
