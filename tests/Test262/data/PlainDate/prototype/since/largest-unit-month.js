// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.since
description: since() with largestUnit: "month" breaks result into months and days
features: [Temporal]
---*/

const d1 = new Temporal.PlainDate(2024, 1, 1);
const d2 = new Temporal.PlainDate(2024, 4, 15);

const result = d2.since(d1, { largestUnit: "month" });
assert.sameValue(result.months, 3, "3 complete months");
assert.sameValue(result.days, 14, "14 remaining days");

const d3 = new Temporal.PlainDate(2024, 1, 15);
const d4 = new Temporal.PlainDate(2024, 4, 10);

const result2 = d4.since(d3, { largestUnit: "month" });
assert.sameValue(result2.months, 2, "2 complete months (day hasn't been reached in April)");
