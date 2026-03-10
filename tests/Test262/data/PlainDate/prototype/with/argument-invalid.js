// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.with
description: TypeError thrown if with() argument contains calendar or timeZone keys
features: [Temporal, arrow-function]
---*/

const date = new Temporal.PlainDate(2000, 5, 2);

assert.throws(
  TypeError,
  () => date.with({ year: 2020, calendar: "iso8601" }),
  "calendar key throws TypeError"
);

assert.throws(
  TypeError,
  () => date.with({ year: 2020, timeZone: "UTC" }),
  "timeZone key throws TypeError"
);
