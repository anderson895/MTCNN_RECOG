<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FACIAL RECOGNITION</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Face API Logic -->
  <script defer src="face_logics/mtcnn-logic.js"></script>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-start p-4">

  <!-- Buttons -->
  <div class="flex gap-4 mb-6">
    <button id="startButton" class="bg-blue-600 text-white px-6 py-2 rounded-md shadow hover:bg-blue-700 transition">
      Launch Facial Recognition
    </button>
    <button id="endAttendance" class="bg-red-600 text-white px-6 py-2 rounded-md shadow hover:bg-red-700 transition">
      END Attendance Taking
    </button>
  </div>

  <!-- Video Container -->
  <div id="video-container" class="relative w-[640px] h-[480px] bg-black rounded-md overflow-hidden shadow-lg">
    <!-- Video and Canvas will be injected here -->
  </div>

  <script>
    let videoStream;
    let labels = ["TUPM-21-0395", "TUPM-21-0396", "TUPM-21-0397", "TUPM-21-0398"];

    document.getElementById("startButton").addEventListener("click", async () => {
      await loadModels();
      await startWebcam();
      startFaceRecognition();
    });

    async function loadModels() {
      try {
        await Promise.all([
          faceapi.nets.ssdMobilenetv1.loadFromUri("models"),
          faceapi.nets.faceLandmark68Net.loadFromUri("models"),
          faceapi.nets.faceRecognitionNet.loadFromUri("models"),
        ]);
        console.log("Face API models loaded");
      } catch (err) {
        alert("Error loading models. Check path and console.");
        console.error(err);
      }
    }

    async function startWebcam() {
      const videoContainer = document.getElementById("video-container");
      videoContainer.innerHTML = "";

      const video = document.createElement("video");
      video.id = "video";
      video.width = 640;
      video.height = 480;
      video.autoplay = true;
      video.className = "w-full h-full object-cover"; // Tailwind class

      videoContainer.appendChild(video);

      try {
        videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = videoStream;

        return new Promise((resolve) => {
          video.onloadedmetadata = () => {
            video.play();
            resolve();
          };
        });
      } catch (error) {
        console.error("Camera access error:", error);
        alert("Unable to access camera");
      }
    }

    async function getLabeledFaceDescriptions() {
      const labeledDescriptors = [];

      for (const label of labels) {
        const descriptions = [];
        for (let i = 1; i <= 5; i++) {
          try {
            const img = await faceapi.fetchImage(`labels/${label}/${i}.png`);
            const detection = await faceapi
              .detectSingleFace(img)
              .withFaceLandmarks()
              .withFaceDescriptor();

            if (detection) {
              descriptions.push(detection.descriptor);
            }
          } catch (e) {
            console.warn(`Skipping image: ${label}/${i}.png`, e);
          }
        }

        if (descriptions.length) {
          labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(label, descriptions));
        }
      }

      return labeledDescriptors;
    }

    async function startFaceRecognition() {
      const video = document.getElementById("video");

      video.addEventListener("play", async () => {
        const canvas = faceapi.createCanvasFromMedia(video);
        const container = document.getElementById("video-container");
        canvas.style.position = "absolute";
        canvas.style.top = "0";
        canvas.style.left = "0";
        container.appendChild(canvas);

        const displaySize = { width: video.width, height: video.height };
        faceapi.matchDimensions(canvas, displaySize);

        const labeledFaceDescriptors = await getLabeledFaceDescriptions();
        const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors);

        setInterval(async () => {
          const detections = await faceapi
            .detectAllFaces(video)
            .withFaceLandmarks()
            .withFaceDescriptors();

          const resizedDetections = faceapi.resizeResults(detections, displaySize);
          canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);

          const results = resizedDetections.map(d =>
            faceMatcher.findBestMatch(d.descriptor)
          );

          results.forEach((result, i) => {
            const box = resizedDetections[i].detection.box;
            const drawBox = new faceapi.draw.DrawBox(box, { label: result.toString() });
            drawBox.draw(canvas);
          });
        }, 100);
      });
    }
  </script>
</body>

</html>
