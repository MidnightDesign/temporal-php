// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.subtract
description: overflow: constrain clamps day when subtracting months
includes: [temporalHelpers.js]
features: [Temporal]
---*/

// Mar 31 - 1 month = Feb 31 -> constrain to Feb 28/29
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2021, 3, 31).subtract({ months: 1 }),
  2021, 2, "M02", 28,
  "Mar 31 - 1 month constrained to Feb 28 (non-leap)"
);

TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2020, 3, 31).subtract({ months: 1 }),
  2020, 2, "M02", 29,
  "Mar 31 - 1 month constrained to Feb 29 (leap)"
);
