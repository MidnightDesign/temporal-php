// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: year is formatted as 4-digit minimum, padding with zeros for small years
features: [Temporal]
---*/

assert.sameValue(new Temporal.PlainDate(1, 1, 1).toString(), "0001-01-01", "year 1 padded to 4 digits");
assert.sameValue(new Temporal.PlainDate(99, 12, 31).toString(), "0099-12-31", "year 99 padded to 4 digits");
assert.sameValue(new Temporal.PlainDate(1000, 6, 15).toString(), "1000-06-15", "year 1000 already 4 digits");
assert.sameValue(new Temporal.PlainDate(9999, 12, 31).toString(), "9999-12-31", "year 9999");
