// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.subtract
description: Duration string argument is accepted by subtract()
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 3, 15);

TemporalHelpers.assertPlainDate(
  date.subtract("P10D"),
  2024, 3, "M03", 5,
  "subtract ISO duration string P10D"
);

TemporalHelpers.assertPlainDate(
  date.subtract("P1M"),
  2024, 2, "M02", 15,
  "subtract ISO duration string P1M"
);

TemporalHelpers.assertPlainDate(
  date.subtract("P1Y2M3D"),
  2023, 1, "M01", 12,
  "subtract ISO duration string P1Y2M3D"
);
