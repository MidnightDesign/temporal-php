// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: Weeks in duration are treated as 7 days each
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 1, 1);

TemporalHelpers.assertPlainDate(
  date.add({ weeks: 1 }),
  2024, 1, "M01", 8,
  "add 1 week = 7 days"
);

TemporalHelpers.assertPlainDate(
  date.add({ weeks: 4 }),
  2024, 1, "M01", 29,
  "add 4 weeks = 28 days"
);

TemporalHelpers.assertPlainDate(
  date.add({ weeks: 5 }),
  2024, 2, "M02", 5,
  "add 5 weeks = 35 days (crosses month boundary)"
);

// Weeks and days combined
TemporalHelpers.assertPlainDate(
  date.add({ weeks: 2, days: 3 }),
  2024, 1, "M01", 18,
  "add 2 weeks + 3 days = 17 days"
);
