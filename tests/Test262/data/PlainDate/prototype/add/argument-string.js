// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: Duration string argument is accepted by add()
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 1, 1);

TemporalHelpers.assertPlainDate(
  date.add("P10D"),
  2024, 1, "M01", 11,
  "add ISO duration string P10D"
);

TemporalHelpers.assertPlainDate(
  date.add("P1M"),
  2024, 2, "M02", 1,
  "add ISO duration string P1M"
);

TemporalHelpers.assertPlainDate(
  date.add("P1Y2M3D"),
  2025, 3, "M03", 4,
  "add ISO duration string P1Y2M3D"
);
