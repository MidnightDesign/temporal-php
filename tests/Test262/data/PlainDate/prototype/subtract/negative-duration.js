// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.subtract
description: Negative duration fields in subtract work like add
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2024, 6, 15);

TemporalHelpers.assertPlainDate(
  date.subtract({ days: -5 }),
  2024, 6, "M06", 20,
  "subtract negative days = add days"
);

TemporalHelpers.assertPlainDate(
  date.subtract({ months: -3 }),
  2024, 9, "M09", 15,
  "subtract negative months = add months"
);
