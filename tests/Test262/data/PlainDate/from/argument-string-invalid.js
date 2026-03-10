// Copyright (C) 2022 Igalia S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: RangeError thrown for invalid ISO strings
features: [Temporal, arrow-function]
---*/

const invalidStrings = [
  "",
  "invalid iso8601",
  "2020-01-00",
  "2020-01-32",
  "2020-02-30",
  "2021-02-29",
  "2020-00-01",
  "2020-13-01",
  "P1Y",
  "-P12Y",
  "2020-W01-1",
  "2020-001",
  "2020-01",
  "+002020-01",
  "01-01",
  "2020-W01",
  "2020-0101",
  "202001-01",
];
for (const arg of invalidStrings) {
  assert.throws(
    RangeError,
    () => Temporal.PlainDate.from(arg),
    `"${arg}" should not be a valid ISO string for a PlainDate`
  );
}
