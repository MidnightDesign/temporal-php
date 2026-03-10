// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: PlainDate.from() accepts strings with time portions (time part is ignored)
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("1976-11-18T00:00:00"),
  1976, 11, "M11", 18,
  "string with midnight time"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("1976-11-18T23:59:59"),
  1976, 11, "M11", 18,
  "string with late time"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("1976-11-18T12:30:00+05:30"),
  1976, 11, "M11", 18,
  "string with time and UTC offset"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from("1976-11-18T15:23:30.000000000Z"),
  1976, 11, "M11", 18,
  "string with full nanosecond precision"
);
