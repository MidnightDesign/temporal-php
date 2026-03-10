// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: Property bag is correctly converted into PlainDate
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2019, month: 10, monthCode: "M10", day: 1 }),
  2019, 10, "M10", 1, "month and monthCode both present"
);
TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 1976, month: 11, day: 18 }),
  1976, 11, "M11", 18, "month only"
);
TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 1976, monthCode: "M11", day: 18 }),
  1976, 11, "M11", 18, "monthCode only"
);
