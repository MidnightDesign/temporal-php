// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: monthCode can be used to set month in with()
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 6, 15);

TemporalHelpers.assertPlainDate(
  date.with({ monthCode: "M01" }),
  2024, 1, "M01", 15,
  "monthCode M01 sets month to January"
);

TemporalHelpers.assertPlainDate(
  date.with({ monthCode: "M12" }),
  2024, 12, "M12", 15,
  "monthCode M12 sets month to December"
);

// month and monthCode can both be present (month takes precedence)
TemporalHelpers.assertPlainDate(
  date.with({ month: 3, monthCode: "M03" }),
  2024, 3, "M03", 15,
  "consistent month and monthCode"
);
