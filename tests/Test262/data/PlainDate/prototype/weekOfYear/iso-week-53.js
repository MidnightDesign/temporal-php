// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.weekofyear
description: weekOfYear for years that have ISO week 53
features: [Temporal]
---*/

// 2020 has 53 ISO weeks (Dec 28 is Monday of week 53)
assert.sameValue(new Temporal.PlainDate(2020, 12, 28).weekOfYear, 53, "2020-12-28 is week 53");
assert.sameValue(new Temporal.PlainDate(2020, 12, 31).weekOfYear, 53, "2020-12-31 is week 53");

// 2015 has 53 ISO weeks
assert.sameValue(new Temporal.PlainDate(2015, 12, 28).weekOfYear, 53, "2015-12-28 is week 53");

// 2021 does not have week 53; last day is in week 52
assert.sameValue(new Temporal.PlainDate(2021, 12, 31).weekOfYear, 52, "2021-12-31 is week 52");

// 2024 first week
assert.sameValue(new Temporal.PlainDate(2024, 1, 1).weekOfYear, 1, "2024-01-01 is week 1");
assert.sameValue(new Temporal.PlainDate(2024, 1, 7).weekOfYear, 1, "2024-01-07 is week 1");
assert.sameValue(new Temporal.PlainDate(2024, 1, 8).weekOfYear, 2, "2024-01-08 is week 2");
