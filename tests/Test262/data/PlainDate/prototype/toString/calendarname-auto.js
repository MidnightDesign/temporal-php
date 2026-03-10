// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: calendarName: "auto" omits the calendar annotation for iso8601
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2000, 5, 2);
assert.sameValue(d.toString({ calendarName: "auto" }), "2000-05-02");
assert.sameValue(d.toString({}), "2000-05-02");
