<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Apply for Sports Program - SNNHS SportsHub</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>

<body>

    <!-- =====================================================
         HEADER
         ===================================================== -->

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

            <span class="brand-text">

                <span class="site-name">
                    SNNHS SportsHub
                </span>

                <span class="site-school">
                    Surigao del Norte National High School
                </span>

            </span>

        </a>


        <div class="header-actions">

            <a
                class="login-button"
                href="{{ route('login') }}"
            >
                Login
            </a>

        </div>

    </header>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="application-page">

        <section class="application-card">


            <!-- Application Heading -->

            <div class="application-heading">

                <div class="application-icon">
                    🏆
                </div>

                <h1>
                    Sports Program Application
                </h1>

                <p>
                    Submit your information and all eligibility
                    documents for the Sports Coordinator's review.
                    This application does not create an athlete account.
                </p>

            </div>


            <!-- Information Notice -->

            <div class="application-info">

                <strong>
                    Before you apply
                </strong>

                <p>
                    Please make sure that all information is correct
                    and that you have the required eligibility documents
                    ready before submitting your application.
                </p>

            </div>


            <!-- Success Message -->

            @if (session('submitted'))

                <div class="success-message">

                    <strong>
                        Application Submitted Successfully
                    </strong>

                    <p>
                        Your complete application was submitted.
                        The Sports Coordinator will review it before
                        any athlete account is created.
                    </p>

                </div>

            @endif


            <!-- Error Messages -->

            @if ($errors->any())

                <div class="form-errors">

                    <strong>
                        Please correct the following:
                    </strong>

                    <ul>

                        @foreach ($errors->all() as $error)

                            <li>
                                {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            @endif


            <!-- =================================================
                 APPLICATION FORM
                 ================================================= -->

            <form
                class="application-form"
                method="POST"
                action="{{ route('application.store') }}"
                enctype="multipart/form-data"
            >

                @csrf


                <!-- =================================================
                     SECTION 1 - PERSONAL INFORMATION
                     ================================================= -->

                <section class="form-section">

                    <div class="form-section-heading">

                        <div class="form-section-number">
                            1
                        </div>

                        <div>

                            <h2>
                                Personal Information
                            </h2>

                            <p>
                                Provide your basic student information.
                            </p>

                        </div>

                    </div>


                    <!-- Full Name -->

                    <label>

                        <span>
                            Full Name <b>*</b>
                        </span>

                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Enter your full name"
                            required
                        >

                    </label>


                    <!-- Student ID + Grade -->

                    <div class="form-row">

                        <label>

                            <span>
                                Student ID <b>*</b>
                            </span>

                            <input
                                type="text"
                                name="student_id"
                                value="{{ old('student_id') }}"
                                placeholder="Enter your student ID"
                                required
                            >

                        </label>


                        <label>

                            <span>
                                Grade Level <b>*</b>
                            </span>

                            <input
                                type="text"
                                name="grade"
                                value="{{ old('grade') }}"
                                placeholder="e.g., Grade 11"
                                required
                            >

                        </label>

                    </div>


                    <!-- Email + Gender -->

                    <div class="form-row">

                        <label>

                            <span>
                                Email Address <b>*</b>
                            </span>

                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="Enter your email address"
                                required
                            >

                        </label>


                        <label>

                            <span>
                                Gender <b>*</b>
                            </span>

                            <select
                                name="gender"
                                required
                            >

                                <option value="">
                                    Choose gender
                                </option>

                                @foreach ([
                                    'Male',
                                    'Female',
                                    'Prefer not to say'
                                ] as $gender)

                                    <option
                                        value="{{ $gender }}"
                                        @selected(old('gender') === $gender)
                                    >
                                        {{ $gender }}
                                    </option>

                                @endforeach

                            </select>

                        </label>

                    </div>

                </section>


                <!-- =================================================
                     SECTION 2 - SPORTS INFORMATION
                     ================================================= -->

                <section class="form-section">

                    <div class="form-section-heading">

                        <div class="form-section-number">
                            2
                        </div>

                        <div>

                            <h2>
                                Sports Information
                            </h2>

                            <p>
                                Select the sports program you want to join.
                            </p>

                        </div>

                    </div>


                    <label>

                        <span>
                            Preferred Sport <b>*</b>
                        </span>

                        <select
                            name="sport_id"
                            required
                        >

                            <option value="">
                                Choose a sport
                            </option>

                            @foreach ($sports as $sport)

                                <option
                                    value="{{ $sport->id }}"
                                    @selected((string) old('sport_id') === (string) $sport->id)
                                >
                                    {{ $sport->name }}
                                </option>

                            @endforeach

                        </select>

                    </label>

                </section>


                <!-- =================================================
                     SECTION 3 - ELIGIBILITY DOCUMENTS
                     ================================================= -->

                <section class="form-section">

                    <div class="form-section-heading">

                        <div class="form-section-number">
                            3
                        </div>

                        <div>

                            <h2>
                                Eligibility Documents
                            </h2>

                            <p>
                                Upload the required documents for verification.
                            </p>

                        </div>

                    </div>


                    <!-- Document Notice -->

                    <div class="document-notice">

                        <strong>
                            Required Documents
                        </strong>

                        <p>
                            Please upload all three required documents.
                        </p>

                        <small>
                            Accepted formats: PDF, JPG, JPEG, and PNG.
                            Maximum file size: 5 MB per document.
                            Documents are stored privately for administrator review.
                        </small>

                    </div>


                    <!-- Medical Certificate -->

                    <label class="file-field">

                        <span>
                            Medical Certificate <b>*</b>
                        </span>

                        <input
                            type="file"
                            name="medical_certificate"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        >

                        <small>
                            Upload your valid medical certificate.
                        </small>

                    </label>


                    <!-- Birth Certificate -->

                    <label class="file-field">

                        <span>
                            PSA Birth Certificate <b>*</b>
                        </span>

                        <input
                            type="file"
                            name="birth_certificate"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        >

                        <small>
                            Upload your PSA birth certificate.
                        </small>

                    </label>


                    <!-- Parent Consent -->

                    <label class="file-field">

                        <span>
                            Parent's or Guardian's Consent Form <b>*</b>
                        </span>

                        <input
                            type="file"
                            name="parent_consent"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        >

                        <small>
                            Upload the signed consent form.
                        </small>

                    </label>

                </section>


                <!-- =================================================
                     SUBMIT
                     ================================================= -->

                <div class="application-submit">

                    <p>
                        By submitting this application, you confirm that
                        the information and documents provided are accurate
                        and complete.
                    </p>

                    <button
                        class="primary-button"
                        type="submit"
                    >
                        Submit Application
                    </button>

                </div>

            </form>

        </section>

    </main>


    <!-- =====================================================
         FOOTER
         ===================================================== -->

    <footer class="site-footer">

        <div class="footer-content">

            <div class="footer-brand">

                <img
                    src="{{ asset('images/snnhs logo.png') }}"
                    alt="SNNHS logo"
                >

                <div>

                    <strong>
                        SNNHS SportsHub
                    </strong>

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