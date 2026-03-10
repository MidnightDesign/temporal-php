// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.until
description: until() with largestUnit: "year" breaks result into years, months, days
features: [Temporal]
---*/

const d1 = new Temporal.PlainDate(2020, 3, 15);
const d2 = new Temporal.PlainDate(2022, 5, 20);

const result = d1.until(d2, { largestUnit: "year" });
assert.sameValue(result.years, 2, "2 years");
assert.sameValue(result.months, 2, "2 months");
assert.sameValue(result.days, 5, "5 remaining days");

// Same month, different day
const d3 = new Temporal.PlainDate(2020, 3, 15);
const d4 = new Temporal.PlainDate(2021, 3, 10);

const result2 = d3.until(d4, { largestUnit: "year" });
assert.sameValue(result2.years, 0, "0 full years (day hasn't been reached)");
assert.sameValue(result2.months, 11, "11 months");
