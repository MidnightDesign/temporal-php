// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.dayofweek
description: All 7 weekdays return the correct ISO day number (1=Mon, 7=Sun)
features: [Temporal]
---*/

// 2024-01-08 is a Monday (dayOfWeek 1)
assert.sameValue(new Temporal.PlainDate(2024, 1, 8).dayOfWeek, 1, "Monday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 9).dayOfWeek, 2, "Tuesday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 10).dayOfWeek, 3, "Wednesday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 11).dayOfWeek, 4, "Thursday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 12).dayOfWeek, 5, "Friday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 13).dayOfWeek, 6, "Saturday");
assert.sameValue(new Temporal.PlainDate(2024, 1, 14).dayOfWeek, 7, "Sunday");
