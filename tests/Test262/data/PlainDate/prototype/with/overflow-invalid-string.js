// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: RangeError thrown when overflow option has an invalid string value
features: [Temporal, arrow-function]
---*/

const original = new Temporal.PlainDate(1976, 11, 18);
const invalidValues = ["CONSTRAIN", "balance", "other string"];
for (const overflow of invalidValues) {
  assert.throws(
    RangeError,
    () => original.with({ year: 2019 }, { overflow }),
    `overflow: "${overflow}" should throw RangeError`
  );
}
