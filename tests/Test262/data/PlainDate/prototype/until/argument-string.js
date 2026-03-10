// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.until
description: String argument is coerced to PlainDate
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2024, 1, 1);

assert.sameValue(d.until("2024-01-15").days, 14, "until ISO string");
assert.sameValue(d.until("2023-12-25").days, -7, "until past ISO string (negative)");
