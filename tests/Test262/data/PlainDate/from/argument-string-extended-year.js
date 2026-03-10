// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: Extended year (6-digit) strings are accepted by PlainDate.from
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("+001976-11-18"),
  1976, 11, "M11", 18,
  "positive extended year"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("+002020-01-01"),
  2020, 1, "M01", 1,
  "positive extended year 2020"
);
