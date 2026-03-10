// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.add
description: overflow: reject throws RangeError when result day is out of range for the month
features: [Temporal, arrow-function]
---*/

assert.throws(
  RangeError,
  () => new Temporal.PlainDate(2021, 1, 31).add({ months: 1 }, { overflow: "reject" }),
  "Jan 31 + 1 month = Feb 31 with reject throws"
);

assert.throws(
  RangeError,
  () => new Temporal.PlainDate(2021, 10, 31).add({ months: 1 }, { overflow: "reject" }),
  "Oct 31 + 1 month = Nov 31 with reject throws"
);

// But valid dates don't throw
const result = new Temporal.PlainDate(2021, 1, 28).add({ months: 1 }, { overflow: "reject" });
assert.sameValue(result.toString(), "2021-02-28", "Jan 28 + 1 month = Feb 28");
