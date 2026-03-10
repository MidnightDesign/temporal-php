// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: overflow: constrain clamps day when adding months
includes: [temporalHelpers.js]
features: [Temporal]
---*/

// Jan 31 + 1 month = Feb 31 -> constrain to Feb 28/29
TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2021, 1, 31).add({ months: 1 }),
  2021, 2, "M02", 28,
  "Jan 31 + 1 month constrained to Feb 28 (non-leap)"
);

TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2020, 1, 31).add({ months: 1 }),
  2020, 2, "M02", 29,
  "Jan 31 + 1 month constrained to Feb 29 (leap)"
);

TemporalHelpers.assertPlainDate(
  new Temporal.PlainDate(2021, 1, 31).add({ months: 1 }, { overflow: "constrain" }),
  2021, 2, "M02", 28,
  "explicit overflow: constrain"
);
