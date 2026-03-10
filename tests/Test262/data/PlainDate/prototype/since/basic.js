// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.since
description: Basic since() operations returning days
features: [Temporal]
---*/

// Same date → zero duration
const d = new Temporal.PlainDate(2000, 1, 1);
assert.sameValue(d.since(d).days, 0, "since same date is 0 days");

// Simple day difference
const d1 = new Temporal.PlainDate(2024, 1, 1);
const d2 = new Temporal.PlainDate(2024, 1, 15);
assert.sameValue(d2.since(d1).days, 14, "14 days since earlier date");

// Cross month boundary
const d3 = new Temporal.PlainDate(2024, 1, 25);
const d4 = new Temporal.PlainDate(2024, 2, 5);
assert.sameValue(d4.since(d3).days, 11, "11 days across January/February");

// Negative (earlier.since(later) should be negative)
assert.sameValue(d1.since(d2).days, -14, "negative when other is later");
