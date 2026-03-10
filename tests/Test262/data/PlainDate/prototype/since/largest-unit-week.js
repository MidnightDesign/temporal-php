// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.since
description: since() with largestUnit: "week" breaks result into weeks and days
features: [Temporal]
---*/

const d1 = new Temporal.PlainDate(2024, 1, 1);
const d2 = new Temporal.PlainDate(2024, 1, 22);

const result = d2.since(d1, { largestUnit: "week" });
assert.sameValue(result.weeks, 3, "3 complete weeks");
assert.sameValue(result.days, 0, "0 remaining days");

const d3 = new Temporal.PlainDate(2024, 1, 25);
const result2 = d3.since(d1, { largestUnit: "week" });
assert.sameValue(result2.weeks, 3, "3 complete weeks");
assert.sameValue(result2.days, 3, "3 remaining days");
