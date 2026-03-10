// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.since
description: since() with largestUnit: "year" breaks result into years, months, days
features: [Temporal]
---*/

const d1 = new Temporal.PlainDate(2020, 3, 15);
const d2 = new Temporal.PlainDate(2022, 5, 20);

const result = d2.since(d1, { largestUnit: "year" });
assert.sameValue(result.years, 2, "2 years");
assert.sameValue(result.months, 2, "2 months");
assert.sameValue(result.days, 5, "5 remaining days");
