// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.inleapyear
description: inLeapYear returns correct boolean for various years
features: [Temporal]
---*/

assert.sameValue(new Temporal.PlainDate(2020, 1, 1).inLeapYear, true, "2020 (divisible by 4)");
assert.sameValue(new Temporal.PlainDate(2021, 1, 1).inLeapYear, false, "2021 (not divisible by 4)");
assert.sameValue(new Temporal.PlainDate(1900, 1, 1).inLeapYear, false, "1900 (century, not leap)");
assert.sameValue(new Temporal.PlainDate(2000, 1, 1).inLeapYear, true, "2000 (400-year, leap)");
assert.sameValue(new Temporal.PlainDate(2100, 1, 1).inLeapYear, false, "2100 (century, not leap)");
assert.sameValue(new Temporal.PlainDate(2400, 1, 1).inLeapYear, true, "2400 (400-year, leap)");
assert.sameValue(new Temporal.PlainDate(1976, 1, 1).inLeapYear, true, "1976 (leap)");
assert.sameValue(new Temporal.PlainDate(1977, 1, 1).inLeapYear, false, "1977 (non-leap)");
