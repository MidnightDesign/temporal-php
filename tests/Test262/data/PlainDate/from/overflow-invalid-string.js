// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: RangeError thrown when overflow option has an invalid string value
features: [Temporal, arrow-function]
---*/

const d = Temporal.PlainDate.from("2019-10-29");
const bag = { year: 2019, month: 10, day: 29 };

const invalidValues = ["CONSTRAIN", "balance", "other string"];
for (const overflow of invalidValues) {
  assert.throws(
    RangeError,
    () => Temporal.PlainDate.from(d, { overflow }),
    `overflow: "${overflow}" (PlainDate input)`
  );
  assert.throws(
    RangeError,
    () => Temporal.PlainDate.from(bag, { overflow }),
    `overflow: "${overflow}" (property bag input)`
  );
  assert.throws(
    RangeError,
    () => Temporal.PlainDate.from("2019-10-29", { overflow }),
    `overflow: "${overflow}" (string input)`
  );
}
