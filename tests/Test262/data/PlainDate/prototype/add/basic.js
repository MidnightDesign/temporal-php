// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: Basic add() operations with days, months, years, and weeks
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(1976, 11, 18);

TemporalHelpers.assertPlainDate(
  date.add({ days: 10 }),
  1976, 11, "M11", 28,
  "add 10 days"
);

TemporalHelpers.assertPlainDate(
  date.add({ days: 30 }),
  1976, 12, "M12", 18,
  "add 30 days (cross month boundary)"
);

TemporalHelpers.assertPlainDate(
  date.add({ months: 2 }),
  1977, 1, "M01", 18,
  "add 2 months"
);

TemporalHelpers.assertPlainDate(
  date.add({ years: 3 }),
  1979, 11, "M11", 18,
  "add 3 years"
);

TemporalHelpers.assertPlainDate(
  date.add({ weeks: 2 }),
  1976, 12, "M12", 2,
  "add 2 weeks (14 days)"
);

TemporalHelpers.assertPlainDate(
  date.add({ years: 1, months: 2, days: 5 }),
  1978, 1, "M01", 23,
  "add 1 year, 2 months, 5 days"
);
