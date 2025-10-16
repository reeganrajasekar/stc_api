<aside class="left-sidebar">
    <div>
        <div class="brand-logo d-flex align-items-center justify-content-between bg-light">
            <a href="/admin/user">
                <img src="/admin/assets/images/logos/logo.png" alt="" style="height:75px">
            </a>
            <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
                <i class="ti ti-x fs-8"></i>
            </div>
        </div>
        <nav class="sidebar-nav scroll-sidebar mt-3" data-simplebar="" style="padding-top: 0px !important">
            <ul id="sidebarnav">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Analytics</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/" aria-expanded="false">
                        <span>
                            <i class="ti ti-layout-grid"></i>
                        </span>
                        <span class="hide-menu">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/users.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-users"></i>
                        </span>
                        <span class="hide-menu">Users</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/enterprise.php" aria-expanded="false">
                        <span>
                          <i class="ri-building-line" style="font-size: 20px"></i>
                        </span>
                        <span class="hide-menu">Enterprise</span>
                    </a>
                </li>
                <!-- <hr class="my-2"> -->
                <!-- <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Course Management</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/courses.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-book"></i>
                        </span>
                        <span class="hide-menu">Courses</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/lessons.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-file-text"></i>
                        </span>
                        <span class="hide-menu">Lessons</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/categories.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-category"></i>
                        </span>
                        <span class="hide-menu">Categories</span>
                    </a>
                </li> -->
                <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Learning Activities</span>
                </li>
               
                <li class="sidebar-item">
                    <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false" data-bs-toggle="collapse" data-bs-target="#listeningMenu" aria-controls="listeningMenu">
                        <span>
                            <i class="ti ti-headphones"></i>
                        </span>
                        <span class="hide-menu">Listening</span>
                       
                    </a>
                    <ul id="listeningMenu" class="collapse first-level submenu" data-bs-parent="#sidebarnav">
                        <li class="sidebar-item">
                            <a href="/admin/user/listening/conversation.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Conversation</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="/admin/user/listening/differentiation.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Differentiation</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="/admin/user/listening/missingwords.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Missing Words</span>
                            </a>
                        </li>
                         <li class="sidebar-item">
                            <a href="/admin/user/listening/picturecapture.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Picture Identify</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false" data-bs-toggle="collapse" data-bs-target="#speakingMenu" aria-controls="speakingMenu">
                        <span>
                            <i class="ti ti-microphone"></i>
                        </span>
                        <span class="hide-menu">Speaking</span>
                    </a>
                    <ul id="speakingMenu" class="collapse first-level submenu" data-bs-parent="#sidebarnav">
                        <li class="sidebar-item">
                            <a href="/admin/user/speaking/repeatafterme.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Repeat After Me</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="/admin/user/speaking/storyin20.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Story in 20</span>
                            </a>
                        </li>
                          <li class="sidebar-item">
                            <a href="/admin/user/speaking/picturecapture_speaking.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Picture Capture</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false" data-bs-toggle="collapse" data-bs-target="#readingMenu" aria-controls="readingMenu">
                        <span>
                            <i class="ti ti-book"></i>
                        </span>
                        <span class="hide-menu">Reading</span>
                    </a>
                    <ul id="readingMenu" class="collapse first-level submenu" data-bs-parent="#sidebarnav">
                        <li class="sidebar-item">
                            <a href="/admin/user/reading/readallowed.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Read Allowed</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="/admin/user/reading/speedreader.php" class="sidebar-link submenu-link">
                                 <span class="hide-menu">Speed Reader</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/quizzes.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-help-circle"></i>
                        </span>
                        <span class="hide-menu">Quizzes</span>
                    </a>
                </li> -->

                <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Videos</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/myvideos/videos.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-video"></i>
                        </span>
                        <span class="hide-menu">My Videos</span>
                    </a>
                </li>
                 <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Phrases</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/phrases/phrases.php" aria-expanded="false">
                        <span>
                           <i class="ri-quill-pen-line" style="font-size: 18px"></i>
                        </span>
                        <span class="hide-menu">Phrases</span>
                    </a>
                </li>
             
                <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Books</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/books/books.php" aria-expanded="false">
                        <span>
                        <i class="ri-book-2-line" style="font-size: 19px"></i>
                        </span>
                        <span class="hide-menu">My Books</span>
                    </a>
                </li>

                <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">Courses</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/courses/courses.php" aria-expanded="false">
                        <span>
                        <i class="ri-presentation-line" style="font-size: 19px"></i>
                        </span>
                        <span class="hide-menu">Courses</span>
                    </a>
                </li>
                <li class="sidebar-item pb-2">
                    <a class="sidebar-link" href="/admin/user/courses/lessons.php" aria-expanded="false">
                        <span>
                        <i class="ri-slideshow-line" style="font-size: 19px"></i>
                        </span>
                        <span class="hide-menu">Lesson</span>
                    </a>
                </li>
                  <li class="sidebar-item pb-2">
                   
                </li>
                <!-- <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">User Progress</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/enrollments.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-user-check"></i>
                        </span>
                        <span class="hide-menu">Enrollments</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/progress.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-chart-line"></i>
                        </span>
                        <span class="hide-menu">Progress</span>
                    </a>
                </li>
             
                <hr class="my-2">
                <li class="nav-small-cap mt-0">
                    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                    <span class="hide-menu">System</span>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/subscriptions.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-credit-card"></i>
                        </span>
                        <span class="hide-menu">Subscriptions</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/points.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-star"></i>
                        </span>
                        <span class="hide-menu">Points & Rewards</span>
                    </a>
                </li> -->
                <!-- <li class="sidebar-item">
                    <a class="sidebar-link" href="/admin/user/activity.php" aria-expanded="false">
                        <span>
                            <i class="ti ti-activity"></i>
                        </span>
                        <span class="hide-menu">Activity Logs</span>
                    </a>
                </li> -->
            </ul>
        </nav>
    </div>
</aside>

<style>
/* Simple Dropdown Animation */
.sidebar-item .has-arrow .arrow-icon {
    transition: transform 0.2s ease;
}

.sidebar-item .has-arrow[aria-expanded="true"] .arrow-icon {
    transform: rotate(90deg);
}

.submenu-link {
    padding-left: 50px !important;
}

.submenu-link:hover {
    background-color: rgba(13, 110, 253, 0.1);
    border-radius: 4px;
}
</style>