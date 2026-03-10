// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-get-temporal.plaindate.prototype.monthsinyear
description: Basic tests for monthsInYear.
features: [Temporal]
---*/

assert.sameValue((new Temporal.PlainDate(1976, 11, 18)).monthsInYear, 12);
assert.sameValue((new Temporal.PlainDate(1234, 7, 15)).monthsInYear, 12);
assert.sameValue(Temporal.PlainDate.from('2019-03-18').monthsInYear, 12);
assert.sameValue(Temporal.PlainDate.from('2020-06-30').monthsInYear, 12);
