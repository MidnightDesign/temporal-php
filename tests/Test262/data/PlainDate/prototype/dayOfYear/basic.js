// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-get-temporal.plaindate.prototype.dayofyear
description: Basic tests for dayOfYear.
features: [Temporal]
---*/

for (let i = 14; i <= 20; ++i) {
  const plainDate = new Temporal.PlainDate(1976, 11, i);
  // 1976 is a leap year: cumDays before Nov = 304 + 1 (leap) = 305
  assert.sameValue(plainDate.dayOfYear, 305 + i, `${plainDate} day of year`);
}
assert.sameValue((new Temporal.PlainDate(2000, 1, 1)).dayOfYear, 1, "jan 1 leap");
assert.sameValue((new Temporal.PlainDate(2001, 1, 1)).dayOfYear, 1, "jan 1 non-leap");
assert.sameValue((new Temporal.PlainDate(2000, 2, 15)).dayOfYear, 46, "feb 15 leap");
assert.sameValue((new Temporal.PlainDate(2001, 2, 15)).dayOfYear, 46, "feb 15 non-leap");
assert.sameValue((new Temporal.PlainDate(2000, 3, 15)).dayOfYear, 75, "mar 15 leap");
assert.sameValue((new Temporal.PlainDate(2001, 3, 15)).dayOfYear, 74, "mar 15 non-leap");
assert.sameValue((new Temporal.PlainDate(2000, 12, 31)).dayOfYear, 366, "dec 31 leap");
assert.sameValue((new Temporal.PlainDate(2001, 12, 31)).dayOfYear, 365, "dec 31 non-leap");
assert.sameValue(Temporal.PlainDate.from('2019-03-15').dayOfYear, 74);
assert.sameValue(Temporal.PlainDate.from('2020-03-15').dayOfYear, 75);
