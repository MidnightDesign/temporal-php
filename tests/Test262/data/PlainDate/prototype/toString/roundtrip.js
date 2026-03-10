// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: PlainDate.from(date.toString()) roundtrip always recovers the original date
includes: [temporalHelpers.js]
features: [Temporal]
---*/

const dates = [
  new Temporal.PlainDate(1976, 11, 18),
  new Temporal.PlainDate(2000, 1, 1),
  new Temporal.PlainDate(2024, 2, 29),
  new Temporal.PlainDate(9999, 12, 31),
];

for (const original of dates) {
  const roundtrip = Temporal.PlainDate.from(original.toString());
  TemporalHelpers.assertPlainDate(
    roundtrip,
    original.year, original.month, original.monthCode, original.day,
    "roundtrip via toString/from"
  );
}
