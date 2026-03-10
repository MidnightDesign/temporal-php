// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: with() on leap-year dates handles Feb 29 via overflow constrain/reject
includes: [temporalHelpers.js]
features: [Temporal, arrow-function]
---*/

// Changing from leap year to non-leap: Feb 29 constrained to Feb 28
const leapFeb29 = new Temporal.PlainDate(2024, 2, 29); // 2024 is a leap year

TemporalHelpers.assertPlainDate(
  leapFeb29.with({ year: 2025 }),
  2025, 2, "M02", 28,
  "Feb 29 in leap year, change year to non-leap: day constrained to 28"
);

TemporalHelpers.assertPlainDate(
  leapFeb29.with({ year: 2028 }), // 2028 is also a leap year
  2028, 2, "M02", 29,
  "Feb 29 in leap year, change to another leap year: day stays 29"
);

// Reject should throw for non-leap Feb 29
assert.throws(
  RangeError,
  () => leapFeb29.with({ year: 2025 }, { overflow: "reject" }),
  "Feb 29 to non-leap year with overflow:reject throws"
);
