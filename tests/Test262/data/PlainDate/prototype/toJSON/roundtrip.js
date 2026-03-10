// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tojson
description: toJSON returns the same string as toString for ISO 8601 calendar
features: [Temporal]
---*/

const dates = [
  new Temporal.PlainDate(1976, 11, 18),
  new Temporal.PlainDate(2000, 1, 1),
  new Temporal.PlainDate(2024, 2, 29),
];

for (const d of dates) {
  assert.sameValue(d.toJSON(), d.toString(), "toJSON equals toString");
}
