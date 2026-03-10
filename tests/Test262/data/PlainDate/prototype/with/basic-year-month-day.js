// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: Basic functionality of with() to override year, month, or day
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const original = new Temporal.PlainDate(1976, 11, 18);

TemporalHelpers.assertPlainDate(
  original.with({ year: 2019 }),
  2019, 11, "M11", 18,
  "override year"
);

TemporalHelpers.assertPlainDate(
  original.with({ month: 5 }),
  1976, 5, "M05", 18,
  "override month"
);

TemporalHelpers.assertPlainDate(
  original.with({ day: 5 }),
  1976, 11, "M11", 5,
  "override day"
);

TemporalHelpers.assertPlainDate(
  original.with({ year: 2019, month: 5, day: 5 }),
  2019, 5, "M05", 5,
  "override all fields"
);

TemporalHelpers.assertPlainDate(
  original.with({ monthCode: "M09" }),
  1976, 9, "M09", 18,
  "override via monthCode"
);
