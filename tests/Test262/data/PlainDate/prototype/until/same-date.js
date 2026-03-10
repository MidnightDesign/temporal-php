// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.until
description: until() returns zero duration for same date
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2024, 6, 15);

const zero = d.until(d);
assert.sameValue(zero.years, 0, "years is 0");
assert.sameValue(zero.months, 0, "months is 0");
assert.sameValue(zero.weeks, 0, "weeks is 0");
assert.sameValue(zero.days, 0, "days is 0");
assert.sameValue(zero.blank, true, "blank is true");
