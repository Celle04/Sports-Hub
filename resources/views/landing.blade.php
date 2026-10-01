<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SNNHS SportsHub</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>

<body>

    <!-- =====================================================
         HEADER / NAVIGATION
    ====================================================== -->

    <header class="site-header">

        <a
            class="site-brand"
            href="{{ url('/') }}"
            aria-label="SNNHS SportsHub home"
        >
            <img
                class="site-logo"
                src="{{ asset('images/snnhs logo.png') }}"
                alt="SNNHS logo"
            >

            <div class="brand-text">
                <span class="site-name">SNNHS SportsHub</span>
                <span class="site-school">
                    Surigao del Norte National High School
                </span>
            </div>
        </a>


        <nav class="main-nav" aria-label="Main navigation">

            <a href="{{ url('/') }}" class="nav-link active">
                Home
            </a>

            <a href="#announcements" class="nav-link">
                Announcements
            </a>

            <a href="#programs" class="nav-link">
                Sports Programs
            </a>

            <a href="#about" class="nav-link">
                About
            </a>

        </nav>


        <div class="header-actions">

            <a
                class="login-button"
                href="{{ route('login') }}"
            >
                Login
            </a>

            <a
                class="header-apply"
                href="{{ route('application.create') }}"
            >
                Apply Now
            </a>

        </div>

    </header>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main>


        <!-- =================================================
             HERO SECTION
        ================================================== -->

        <section class="hero">

            <div class="hero-content">

                <div class="hero-icon">
                    ✓
                </div>

                <h1>
                    Where Sports <span>Thrive</span>
                </h1>

                <p>
                    Discover school sports, connect with athletes,
                    explore programs, and build your potential
                    through athletics.
                </p>


                <div class="hero-buttons">

                    <a
                        class="hero-primary-button"
                        href="{{ route('application.create') }}"
                    >
                        Apply for Sports
                    </a>

                    <a
                        class="hero-secondary-button"
                        href="#programs"
                    >
                        Explore Programs
                    </a>

                </div>

            </div>

        </section>


        <!-- =================================================
             SPORTS IMAGE / BANNER
        ================================================== -->

        <section class="sports-banner">

            <div class="sports-banner-overlay">

                <div class="banner-content">
                    <span class="banner-label">
                        SNNHS SPORTS
                    </span>

                    <h2>
                        Empowering Athletes.
                        <br>
                        Building Champions.
                    </h2>

                    <p>
                        A central hub for sports activities,
                        athletes, coaches, events, and school
                        athletic programs.
                    </p>
                </div>

            </div>

        </section>


        <!-- =================================================
             ANNOUNCEMENTS
        ================================================== -->

        <section
            class="announcements"
            id="announcements"
        >

            <div class="section-heading">

                <div>
                    <span class="section-label">
                        STAY UPDATED
                    </span>

                    <h2>
                        Latest Announcements
                    </h2>
                </div>

            </div>


            <div class="announcement-grid">

                @forelse($announcements as $announcement)

                    <article class="announcement">

                        <div class="announcement-top">

                            <span class="announcement-date">
                                {{ $announcement->published_at?->format('F j, Y') ?? 'Upcoming' }}
                            </span>

                            @if($announcement->sport)
                                <span class="sport-tag">
                                    {{ $announcement->sport->name }}
                                </span>
                            @endif

                        </div>


                        <h3>
                            {{ $announcement->title }}
                        </h3>


                        <p>
                            {{ $announcement->body }}
                        </p>

                    </article>

                @empty

                    <article class="announcement empty">

                        <div class="announcement-icon">
                            !
                        </div>

                        <h3>
                            No announcements yet
                        </h3>

                        <p>
                            Please check back for sports updates
                            and tryout schedules.
                        </p>

                    </article>

                @endforelse

            </div>

        </section>


        <!-- =================================================
             SPORTS PROGRAMS
        ================================================== -->

        <section
            class="programs"
            id="programs"
        >

            <div class="section-heading centered">

                <span class="section-label">
                    EXPLORE ATHLETICS
                </span>

                <h2>
                    Sports Programs
                </h2>

                <p>
                    Discover the different sports programs
                    available at SNNHS.
                </p>

            </div>


            <div class="program-grid">

                @forelse($sports as $sport)

                    <article class="program">

                        <div class="program-icon">
                            &#127942;
                        </div>

                        <div class="program-content">

                            <h3>
                                {{ $sport->name }}
                            </h3>

                            <p>
                                {{ $sport->description }}
                            </p>

                        </div>

                    </article>

                @empty

                    <article class="program empty">

                        <div class="program-icon">
                            &#127942;
                        </div>

                        <h3>
                            Programs coming soon
                        </h3>

                        <p>
                            New sports programs will be
                            available soon.
                        </p>

                    </article>

                @endforelse

            </div>

        </section>


        <!-- =================================================
             ABOUT / CTA
        ================================================== -->

        <section
            class="about-section"
            id="about"
        >

            <div class="about-content">

                <span class="section-label">
                    SNNHS SPORTSHUB
                </span>

                <h2>
                    Your journey to becoming
                    a champion starts here.
                </h2>

                <p>
                    SportsHub makes it easier for students to
                    discover sports opportunities, submit
                    applications, follow athletic activities,
                    and stay connected with the school's
                    sports community.
                </p>

                <a
                    class="about-button"
                    href="{{ route('application.create') }}"
                >
                    Start Your Application
                    
                </a>

            </div>

        </section>

    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="site-footer">

        <div class="footer-content">

            <div class="footer-brand">

                <img
                    src="{{ asset('images/snnhs logo.png') }}"
                    alt="SNNHS logo"
                >

                <div>
                    <strong>SNNHS SportsHub</strong>

                    <span>
                        Surigao del Norte National High School
                    </span>
                </div>

            </div>


            <div class="footer-text">

                &copy; {{ now()->year }}
                Surigao del Norte National High School.
                All rights reserved.

                <br>

                SportsHub - Empowering Athletes, Building Champions

            </div>

        </div>

    </footer>

</body>

</html>