// Copyright (C) 2021 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.prototype.valueof
description: Basic tests for valueOf().
features: [Temporal]
---*/

const plainDate = Temporal.PlainDate.from("1963-02-13");

assert.throws(TypeError, () => plainDate.valueOf(), "valueOf");
