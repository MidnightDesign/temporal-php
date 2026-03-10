// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.equals
description: A PlainDate always equals itself and the same ISO string
features: [Temporal]
---*/

const dates = [
  new Temporal.PlainDate(2000, 1, 1),
  new Temporal.PlainDate(1976, 11, 18),
  new Temporal.PlainDate(2024, 2, 29),
  new Temporal.PlainDate(9999, 12, 31),
  new Temporal.PlainDate(1, 1, 1),
];

for (const d of dates) {
  assert.sameValue(d.equals(d), true, "reflexive: date equals itself");
  assert.sameValue(d.equals(d.toString()), true, "date equals its ISO string");
}
