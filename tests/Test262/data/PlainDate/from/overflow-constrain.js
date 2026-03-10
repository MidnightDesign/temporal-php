// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: overflow: "constrain" clamps out-of-range day/month values
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2021, month: 1, day: 50 }, { overflow: "constrain" }),
  2021, 1, "M01", 31,
  "day 50 in January constrained to 31"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2021, month: 2, day: 30 }, { overflow: "constrain" }),
  2021, 2, "M02", 28,
  "day 30 in February 2021 constrained to 28"
);

TemporalHelpers.assertPlainDate(
  Temporal.PlainDate.from({ year: 2020, month: 2, day: 30 }, { overflow: "constrain" }),
  2020, 2, "M02", 29,
  "day 30 in February 2020 (leap year) constrained to 29"
);

assert.throws(
  RangeError,
  () => Temporal.PlainDate.from({ year: 2021, month: 1, day: 32 }, { overflow: "reject" }),
  "overflow: reject throws for day 32"
);
