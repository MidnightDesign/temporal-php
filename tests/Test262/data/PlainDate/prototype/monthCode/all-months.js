// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.monthcode
description: monthCode returns M01 through M12 for all months
features: [Temporal]
---*/

const year = 2021;
const expected = ["M01", "M02", "M03", "M04", "M05", "M06",
                  "M07", "M08", "M09", "M10", "M11", "M12"];
for (let month = 1; month <= 12; month++) {
  assert.sameValue(
    new Temporal.PlainDate(year, month, 1).monthCode,
    expected[month - 1],
    `month ${month}`
  );
}
