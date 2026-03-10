// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: calendarName: "critical" appends the critical calendar annotation
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2000, 5, 2);
assert.sameValue(d.toString({ calendarName: "critical" }), "2000-05-02[!u-ca=iso8601]");

const d2 = new Temporal.PlainDate(1976, 11, 18);
assert.sameValue(d2.toString({ calendarName: "critical" }), "1976-11-18[!u-ca=iso8601]");
