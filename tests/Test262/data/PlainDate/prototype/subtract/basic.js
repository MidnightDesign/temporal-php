// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.subtract
description: Basic subtract() operations with days, months, years, and weeks
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(1976, 11, 18);

TemporalHelpers.assertPlainDate(
  date.subtract({ days: 10 }),
  1976, 11, "M11", 8,
  "subtract 10 days"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ days: 30 }),
  1976, 10, "M10", 19,
  "subtract 30 days (cross month boundary)"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ months: 2 }),
  1976, 9, "M09", 18,
  "subtract 2 months"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ years: 3 }),
  1973, 11, "M11", 18,
  "subtract 3 years"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ weeks: 2 }),
  1976, 11, "M11", 4,
  "subtract 2 weeks (14 days)"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ years: 1, months: 2, days: 5 }),
  1975, 9, "M09", 13,
  "subtract 1 year, 2 months, 5 days"
);
