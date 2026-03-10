// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: Fields not present in the with() argument keep their original values
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 6, 15);

// Only change year; month and day stay the same
TemporalHelpers.assertPlainDate(
  date.with({ year: 2025 }),
  2025, 6, "M06", 15,
  "only year changed"
);

// Only change month; year and day stay the same
TemporalHelpers.assertPlainDate(
  date.with({ month: 3 }),
  2024, 3, "M03", 15,
  "only month changed"
);

// Only change day; year and month stay the same
TemporalHelpers.assertPlainDate(
  date.with({ day: 1 }),
  2024, 6, "M06", 1,
  "only day changed"
);
