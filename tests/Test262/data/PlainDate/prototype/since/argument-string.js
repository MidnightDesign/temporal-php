// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.since
description: String argument is coerced to PlainDate
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2024, 1, 15);

assert.sameValue(d.since("2024-01-01").days, 14, "since ISO string");
assert.sameValue(d.since("2024-02-01").days, -17, "since future ISO string (negative)");
