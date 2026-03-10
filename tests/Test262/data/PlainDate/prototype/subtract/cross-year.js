// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.subtract
description: subtract() correctly handles year boundaries
includes: [temporalHelpers.js]
features: [Temporal]
---*/

// Jan 1 - 1 day → Dec 31 prev year
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2024, 1, 1).subtract({ days: 1 }),
  2023, 12, "M12", 31,
  "Jan 1 - 1 day crosses year boundary"
);

// Jan 1 - 12 months = Jan 1 prev year
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2021, 1, 1).subtract({ months: 12 }),
  2020, 1, "M01", 1,
  "subtracting 12 months = 1 year back"
);

// Jan 1 - 1 year = Jan 1 prev year
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2021, 1, 1).subtract({ years: 1 }),
  2020, 1, "M01", 1,
  "subtract 1 year"
);
