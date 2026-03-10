// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.daysinmonth
description: daysInMonth returns correct values for all months including leap/non-leap February
features: [Temporal]
---*/

// Non-leap year 2021
assert.sameValue(new Temporal.PlainDate(2021, 1, 1).daysInMonth, 31, "January");
assert.sameValue(new Temporal.PlainDate(2021, 2, 1).daysInMonth, 28, "February (non-leap)");
assert.sameValue(new Temporal.PlainDate(2021, 3, 1).daysInMonth, 31, "March");
assert.sameValue(new Temporal.PlainDate(2021, 4, 1).daysInMonth, 30, "April");
assert.sameValue(new Temporal.PlainDate(2021, 5, 1).daysInMonth, 31, "May");
assert.sameValue(new Temporal.PlainDate(2021, 6, 1).daysInMonth, 30, "June");
assert.sameValue(new Temporal.PlainDate(2021, 7, 1).daysInMonth, 31, "July");
assert.sameValue(new Temporal.PlainDate(2021, 8, 1).daysInMonth, 31, "August");
assert.sameValue(new Temporal.PlainDate(2021, 9, 1).daysInMonth, 30, "September");
assert.sameValue(new Temporal.PlainDate(2021, 10, 1).daysInMonth, 31, "October");
assert.sameValue(new Temporal.PlainDate(2021, 11, 1).daysInMonth, 30, "November");
assert.sameValue(new Temporal.PlainDate(2021, 12, 1).daysInMonth, 31, "December");

// Leap year 2020
assert.sameValue(new Temporal.PlainDate(2020, 2, 1).daysInMonth, 29, "February (leap year 2020)");

// Century year (non-leap)
assert.sameValue(new Temporal.PlainDate(1900, 2, 1).daysInMonth, 28, "February 1900 (century, non-leap)");

// 400-year (leap)
assert.sameValue(new Temporal.PlainDate(2000, 2, 1).daysInMonth, 29, "February 2000 (400-year, leap)");
