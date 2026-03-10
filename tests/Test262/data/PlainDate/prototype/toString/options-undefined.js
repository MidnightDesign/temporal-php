// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.tostring
description: Passing undefined as options is the same as not passing options
features: [Temporal]
---*/

const d = new Temporal.PlainDate(2000, 5, 2);
assert.sameValue(d.toString(undefined), "2000-05-02");
assert.sameValue(d.toString({}), "2000-05-02");
