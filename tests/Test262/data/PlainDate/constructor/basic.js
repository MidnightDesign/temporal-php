// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate
description: Basic functionality of the PlainDate constructor
features: [Temporal]
---*/

const d1 = new Temporal.PlainDate(1976, 11, 18);
assert.sameValue(d1.year, 1976);
assert.sameValue(d1.month, 11);
assert.sameValue(d1.day, 18);
assert.sameValue(d1.toString(), "1976-11-18");

const d2 = new Temporal.PlainDate(1914, 2, 23);
assert.sameValue(d2.year, 1914);
assert.sameValue(d2.month, 2);
assert.sameValue(d2.day, 23);
assert.sameValue(d2.toString(), "1914-02-23");

const d3 = new Temporal.PlainDate(2000, 1, 1);
assert.sameValue(d3.year, 2000);
assert.sameValue(d3.month, 1);
assert.sameValue(d3.day, 1);
assert.sameValue(d3.toString(), "2000-01-01");

const d4 = new Temporal.PlainDate(1996, 2, 29);
assert.sameValue(d4.year, 1996);
assert.sameValue(d4.month, 2);
assert.sameValue(d4.day, 29);
assert.sameValue(d4.toString(), "1996-02-29");
