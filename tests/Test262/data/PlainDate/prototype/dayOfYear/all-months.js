// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.dayofyear
description: dayOfYear for the first day of each month in various years
features: [Temporal]
---*/

const year = 2021; // non-leap year

// First day of each month in non-leap year
assert.sameValue(new Temporal.PlainDate(year, 1, 1).dayOfYear, 1, "January 1 is day 1");
assert.sameValue(new Temporal.PlainDate(year, 2, 1).dayOfYear, 32, "February 1 is day 32");
assert.sameValue(new Temporal.PlainDate(year, 3, 1).dayOfYear, 60, "March 1 is day 60");
assert.sameValue(new Temporal.PlainDate(year, 4, 1).dayOfYear, 91, "April 1 is day 91");
assert.sameValue(new Temporal.PlainDate(year, 5, 1).dayOfYear, 121, "May 1 is day 121");
assert.sameValue(new Temporal.PlainDate(year, 6, 1).dayOfYear, 152, "June 1 is day 152");
assert.sameValue(new Temporal.PlainDate(year, 7, 1).dayOfYear, 182, "July 1 is day 182");
assert.sameValue(new Temporal.PlainDate(year, 8, 1).dayOfYear, 213, "August 1 is day 213");
assert.sameValue(new Temporal.PlainDate(year, 9, 1).dayOfYear, 244, "September 1 is day 244");
assert.sameValue(new Temporal.PlainDate(year, 10, 1).dayOfYear, 274, "October 1 is day 274");
assert.sameValue(new Temporal.PlainDate(year, 11, 1).dayOfYear, 305, "November 1 is day 305");
assert.sameValue(new Temporal.PlainDate(year, 12, 1).dayOfYear, 335, "December 1 is day 335");

// Leap year February
assert.sameValue(new Temporal.PlainDate(2020, 2, 29).dayOfYear, 60, "Feb 29 is day 60 in leap year");
assert.sameValue(new Temporal.PlainDate(2020, 3, 1).dayOfYear, 61, "Mar 1 is day 61 in leap year");
assert.sameValue(new Temporal.PlainDate(2020, 12, 31).dayOfYear, 366, "Dec 31 is day 366 in leap year");
