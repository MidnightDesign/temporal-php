// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: RangeError thrown when calendarName option has an invalid string value
features: [Temporal, arrow-function]
---*/

const d = new Temporal.PlainDate(2000, 5, 2);
const invalidValues = ["NEVER", "always\0", "foo", "auto\0", "ALWAYS", "never!"];
for (const calendarName of invalidValues) {
  assert.throws(
    RangeError,
    () => d.toString({ calendarName }),
    `calendarName: "${calendarName}" should throw RangeError`
  );
}
