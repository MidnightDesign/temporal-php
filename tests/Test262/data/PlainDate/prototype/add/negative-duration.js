// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: Negative duration fields work like subtract
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 6, 15);

TemporalHelpers.assertPlainDate(
  date.add({ days: -5 }),
  2024, 6, "M06", 10,
  "add negative days = subtract days"
);

TemporalHelpers.assertPlainDate(
  date.add({ months: -3 }),
  2024, 3, "M03", 15,
  "add negative months = subtract months"
);

TemporalHelpers.assertPlainDate(
  date.add({ years: -1 }),
  2023, 6, "M06", 15,
  "add negative year = subtract year"
);
