# Driver vehicle category — 2026-10-05

M54.1 / R168 adds one required vehicle category to the driver work profile: two-wheeler, three-wheeler, car, van, pickup or truck. This describes the driver's primary vehicle, independently of their transport/errand service categories. No choice is preselected or inferred for existing drivers.

Web work-profile saves and API work-category submissions require an allow-listed category; other roles cannot set it. Ordinary partial API contact updates remain supported. Public Transport & errands directories only list active drivers with a valid selection, including offline drivers. Existing unclassified drivers must update their work profile to appear again. The migration adds a nullable field without fabricating vehicle data. Profile responses supply labels/options to the app, and app/web directory cards display the vehicle.

This change does not alter dispatch matching or availability, nor introduce an application approval process for drivers. Vehicle selection is the prerequisite for the existing directory listing. Deploy backend migration/API before the new app; missing server options leave app submission disabled with an explanatory message. Production rollout remains M8.4/Q7.

The selected vehicle is included in the owner profile and therefore the existing account data export. Account erasure clears it with other profile fields. No additional contact data or vehicle registration identifiers are collected.
