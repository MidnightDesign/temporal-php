// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: add() correctly handles year boundaries
includes: [temporalHelpers.js]
features: [Temporal]
---*/

// Dec 31 + 1 day → Jan 1 next year
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2023, 12, 31).add({ days: 1 }),
  2024, 1, "M01", 1,
  "Dec 31 + 1 day crosses year boundary"
);

// Dec 31 + 366 days (leap year 2024)
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2024, 1, 1).add({ days: 365 }),
  2024, 12, "M12", 31,
  "Jan 1 2024 + 365 days = Dec 31 2024 (leap year)"
);

// Jan 1 + 12 months = Jan 1 next year
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2020, 1, 1).add({ months: 12 }),
  2021, 1, "M01", 1,
  "12 months = 1 year"
);
