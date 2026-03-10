// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.equals
description: PlainDate argument is compared field by field
features: [Temporal]
---*/

const date = new Temporal.PlainDate(2000, 5, 2);

assert.sameValue(date.equals(new Temporal.PlainDate(2000, 5, 2)), true, "same date");
assert.sameValue(date.equals(new Temporal.PlainDate(2000, 5, 3)), false, "different day");
assert.sameValue(date.equals(new Temporal.PlainDate(2000, 6, 2)), false, "different month");
assert.sameValue(date.equals(new Temporal.PlainDate(2001, 5, 2)), false, "different year");

// equals() always returns false for different dates regardless of direction
const d1 = new Temporal.PlainDate(2020, 1, 1);
const d2 = new Temporal.PlainDate(2021, 1, 1);
assert.sameValue(d1.equals(d2), false, "earlier not equal to later");
assert.sameValue(d2.equals(d1), false, "later not equal to earlier");
