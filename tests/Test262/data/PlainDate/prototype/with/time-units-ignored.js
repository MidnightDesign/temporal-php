// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: Time unit fields are ignored in with() for PlainDate
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const original = new Temporal.PlainDate(1976, 11, 18);

TemporalHelpers.assertPlainDate(
  original.with({ year: 2019, hour: 12, minute: 30, second: 45 }),
  2019, 11, "M11", 18,
  "hour/minute/second are ignored"
);

TemporalHelpers.assertPlainDate(
  original.with({ day: 5, millisecond: 500, microsecond: 250, nanosecond: 100 }),
  1976, 11, "M11", 5,
  "millisecond/microsecond/nanosecond are ignored"
);
