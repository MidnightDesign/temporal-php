// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.daysinyear
description: daysInYear returns 365 or 366 for various years
features: [Temporal]
---*/

assert.sameValue(new Temporal.PlainDate(2021, 6, 15).daysInYear, 365, "non-leap year 2021");
assert.sameValue(new Temporal.PlainDate(2020, 6, 15).daysInYear, 366, "leap year 2020");
assert.sameValue(new Temporal.PlainDate(1900, 6, 15).daysInYear, 365, "century year 1900 (non-leap)");
assert.sameValue(new Temporal.PlainDate(2000, 6, 15).daysInYear, 366, "400-year 2000 (leap)");
assert.sameValue(new Temporal.PlainDate(2100, 6, 15).daysInYear, 365, "century year 2100 (non-leap)");
assert.sameValue(new Temporal.PlainDate(2400, 6, 15).daysInYear, 366, "400-year 2400 (leap)");
