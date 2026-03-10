// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.compare
description: compare() returns -1, 0, or 1 based on chronological order
features: [Temporal]
---*/

const earlier = new Temporal.PlainDate(2020, 1, 1);
const later   = new Temporal.PlainDate(2020, 12, 31);
const same    = new Temporal.PlainDate(2020, 1, 1);

assert.sameValue(Temporal.PlainDate.compare(earlier, later), -1, "earlier < later");
assert.sameValue(Temporal.PlainDate.compare(later, earlier), 1, "later > earlier");
assert.sameValue(Temporal.PlainDate.compare(earlier, same), 0, "equal dates");

// Year takes precedence
assert.sameValue(Temporal.PlainDate.compare(new Temporal.PlainDate(2019, 12, 31), new Temporal.PlainDate(2020, 1, 1)), -1, "year comparison");

// Month takes precedence over day
assert.sameValue(Temporal.PlainDate.compare(new Temporal.PlainDate(2020, 6, 30), new Temporal.PlainDate(2020, 7, 1)), -1, "month comparison");
