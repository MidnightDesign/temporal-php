// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: Leap second (second :60) in a string is valid for PlainDate since the time is ignored
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("1976-11-18T23:59:60"),
  1976, 11, "M11", 18,
  "string with leap second (second :60) maps to valid date"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("2016-12-31T23:59:60+00:00"),
  2016, 12, "M12", 31,
  "string with leap second and UTC offset"
);
