/*
 * Team achievements on the admin Achievements form.
 *
 * An achievement is either earned by one athlete or won by a team, so the two
 * fields are mutually exclusive: as soon as a team name is typed the athlete
 * field steps aside, and the hidden flag tells the server which one the
 * record is.
 */
(function () {
    'use strict';

    function setup() {
        var form = document.getElementById('achievement-form');

        if (!form) {
            return;
        }

        var athlete = form.querySelector('#achievement-athlete');
        var team = form.querySelector('#achievement-team-name');
        var flag = form.querySelector('#achievement-is-team');

        if (!athlete || !team || !flag) {
            return;
        }

        function sync() {
            var isTeam = team.value.trim() !== '';

            flag.value = isTeam ? '1' : '0';
            athlete.disabled = isTeam;

            if (isTeam) {
                athlete.removeAttribute('required');
            } else {
                athlete.setAttribute('required', 'required');
            }
        }

        team.addEventListener('input', sync);
        team.addEventListener('change', sync);
        athlete.addEventListener('change', function () {
            if (athlete.value !== '') {
                team.value = '';
            }
            sync();
        });

        sync();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
