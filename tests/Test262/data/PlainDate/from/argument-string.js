// Copyright (C) 2022 Igalia, S.L. All rights reserved.
// This code is governed by the BSD license found in the LICENSE file.

/*---
esid: sec-temporal.plaindate.from
description: various interesting string arguments
includes: [temporalHelpers.js]
features: [Temporal]
---*/

TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("1976-11-18"), 1976, 11, "M11", 18, "basic ISO string");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("2019-06-30"), 2019, 6, "M06", 30, "another ISO string");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("+000050-06-30"), 50, 6, "M06", 30, "extended year positive");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("+010583-06-30"), 10583, 6, "M06", 30, "large positive extended year");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("-010583-06-30"), -10583, 6, "M06", 30, "negative extended year");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("-000333-06-30"), -333, 6, "M06", 30, "small negative extended year");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("19761118"), 1976, 11, "M11", 18, "compact format");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("1976-11-18T152330.1+00:00"), 1976, 11, "M11", 18, "with time and offset");
TemporalHelpers.assertPlainDate(Temporal.PlainDate.from("19761118T15:23:30.1+00:00"), 1976, 11, "M11", 18, "compact with time");
