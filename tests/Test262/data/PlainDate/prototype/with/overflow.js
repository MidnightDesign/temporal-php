// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: overflow option constrains or rejects out-of-range values
includes: [temporalHelpers.js]
features: [Temporal, arrow-function]
---*/

const original = new Temporal.PlainDate(1976, 11, 18);

TemporalHelpers.assertPlainDate(
  original.with({ day: 31 }, { overflow: "constrain" }),
  1976, 11, "M11", 30,
  "day 31 in November constrained to 30"
);

TemporalHelpers.assertPlainDate(
  original.with({ month: 2, day: 30 }, { overflow: "constrain" }),
  1976, 2, "M02", 29,
  "day 30 in February 1976 (leap year) constrained to 29"
);

assert.throws(
  RangeError,
  () => original.with({ day: 31 }, { overflow: "reject" }),
  "day 31 in November should throw with overflow: reject"
);
