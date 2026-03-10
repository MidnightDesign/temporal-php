// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: overflow: undefined defaults to constrain
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2021, month: 1, day: 50 }, { overflow: undefined }),
  2021, 1, "M01", 31,
  "overflow: undefined defaults to constrain"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2021, month: 2, day: 29 }, {}),
  2021, 2, "M02", 28,
  "empty options defaults to constrain"
);
